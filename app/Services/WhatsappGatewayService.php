<?php

declare(strict_types=1);

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Evolution API gateway (global URL/key dari env).
 * Instance per tenant: {prefix}-{tenantId} (default sidbm-12).
 *
 * @see https://docs.evolutionfoundation.com.br/evolution-api/connect-instance
 */
final class WhatsappGatewayService
{
    private const KEY_PHONE = 'whatsapp.pairing_phone';

    private const KEY_ENABLED = 'whatsapp.is_enabled';

    public function __construct(
        private TenantSettingService $settings,
        private TenantContext $context,
        private HttpFactory $http,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->settings->get(self::KEY_ENABLED, false);
    }

    public function isConfigured(): bool
    {
        return $this->baseUrl() !== '' && $this->apiKey() !== '';
    }

    public function getInstance(): string
    {
        $prefix = (string) config('services.evolution.instance_prefix', 'sidbm');
        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'sidbm';

        return $prefix.'-'.$this->context->id();
    }

    public function getPairingPhone(): string
    {
        return (string) ($this->settings->get(self::KEY_PHONE, '') ?: '');
    }

    public function setPairingPhone(string $phone): void
    {
        $this->settings->set(self::KEY_PHONE, $this->normalizePhone($phone));
    }

    public function setEnabled(bool $enabled): void
    {
        $this->settings->set(self::KEY_ENABLED, $enabled, 'bool');
    }

    /**
     * @return array{success:bool,status:string,message:string,state:?string,instance:string,payload?:array<string,mixed>}
     */
    public function connectionState(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'message' => 'EVOLUTION_URL / EVOLUTION_API_KEY belum diisi di environment.',
                'state' => null,
                'instance' => $this->getInstance(),
            ];
        }

        $instance = $this->getInstance();

        try {
            $response = $this->request('get', '/instance/connectionState/'.$instance);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp connectionState failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'network_error',
                'message' => 'Gagal menghubungi server: '.$e->getMessage(),
                'state' => null,
                'instance' => $instance,
            ];
        }

        $payload = $response->json() ?? [];
        $state = $this->extractState($payload);

        if ($response->status() === 404) {
            return [
                'success' => true,
                'status' => 'missing',
                'message' => 'Instance belum dibuat. Pair nomor untuk membuatnya.',
                'state' => 'missing',
                'instance' => $instance,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => 'http_'.$response->status(),
                'message' => 'Server mengembalikan status '.$response->status().'.',
                'state' => $state,
                'instance' => $instance,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        $open = in_array(strtolower((string) $state), ['open', 'connected'], true);

        return [
            'success' => true,
            'status' => $open ? 'open' : 'ok',
            'message' => $open ? 'WhatsApp terhubung.' : 'Status: '.($state ?: 'unknown'),
            'state' => $state,
            'instance' => $instance,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * Pair via Evolution connect-instance with phone number → pairing code.
     *
     * @return array{success:bool,message:string,pairing_code:?string,state:?string,instance:string,payload?:array<string,mixed>}
     */
    public function pairWithPhone(string $phone): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'EVOLUTION_URL / EVOLUTION_API_KEY belum diisi di environment.',
                'pairing_code' => null,
                'state' => null,
                'instance' => $this->getInstance(),
            ];
        }

        $normalized = $this->normalizePhone($phone);
        if ($normalized === '' || strlen($normalized) < 10) {
            return [
                'success' => false,
                'message' => 'Nomor HP tidak valid. Gunakan format 08… atau 62…',
                'pairing_code' => null,
                'state' => null,
                'instance' => $this->getInstance(),
            ];
        }

        $this->setPairingPhone($normalized);
        $instance = $this->getInstance();

        try {
            $this->ensureInstanceExists($instance);
            $response = $this->request(
                'get',
                '/instance/connect/'.$instance,
                query: ['number' => $normalized],
            );
        } catch (\Throwable $e) {
            Log::warning('WhatsApp pair failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Gagal pair: '.$e->getMessage(),
                'pairing_code' => null,
                'state' => null,
                'instance' => $instance,
            ];
        }

        $payload = $response->json() ?? [];
        $code = $this->extractPairingCode($payload);

        if (! $response->successful()) {
            return [
                'success' => false,
                'message' => 'Server mengembalikan status '.$response->status().'.',
                'pairing_code' => $code,
                'state' => $this->extractState($payload),
                'instance' => $instance,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        if ($code === null || $code === '') {
            return [
                'success' => true,
                'message' => 'Permintaan pair dikirim. Buka WhatsApp → Perangkat tertaut → tautkan dengan nomor telepon.',
                'pairing_code' => null,
                'state' => $this->extractState($payload),
                'instance' => $instance,
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        return [
            'success' => true,
            'message' => 'Masukkan kode pairing di WhatsApp (Perangkat tertaut).',
            'pairing_code' => $code,
            'state' => $this->extractState($payload),
            'instance' => $instance,
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * @return array{success:bool,message:string}
     */
    public function sendText(string $phone, string $message): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp gateway belum dikonfigurasi (env).');
        }
        if (! $this->isEnabled()) {
            throw new RuntimeException('WhatsApp gateway belum diaktifkan.');
        }

        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return ['success' => false, 'message' => 'Nomor penerima kosong/tidak valid.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'message' => 'Isi pesan kosong.'];
        }

        $instance = $this->getInstance();
        $url = $this->baseUrl().'/message/sendText/'.$instance;

        $response = $this->http
            ->withHeaders(['apikey' => $this->apiKey()])
            ->timeout($this->timeout())
            ->post($url, [
                'number' => $normalized,
                'text' => $message,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'message' => 'Pesan terkirim.'];
        }

        Log::warning('WhatsApp sendText failed', [
            'tenant_id' => $this->context->id(),
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return [
            'success' => false,
            'message' => 'Gagal mengirim: HTTP '.$response->status(),
        ];
    }

    /**
     * Normalize to digits only; convert 08… → 62…
     */
    public function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }
        if (str_starts_with($digits, '8') && strlen($digits) >= 9 && strlen($digits) <= 13) {
            $digits = '62'.$digits;
        }

        return $digits;
    }

    private function ensureInstanceExists(string $instance): void
    {
        $state = $this->connectionState();
        if (($state['state'] ?? null) !== 'missing' && ($state['status'] ?? '') !== 'missing') {
            return;
        }

        // Create instance if missing (Evolution v2 createInstance).
        try {
            $this->request('post', '/instance/create', body: [
                'instanceName' => $instance,
                'integration' => 'WHATSAPP-BAILEYS',
                'qrcode' => false,
            ]);
        } catch (\Throwable $e) {
            Log::info('WhatsApp create instance skipped/failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, mixed>  $query
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $url = $this->baseUrl().$path;
        $pending = $this->http
            ->withHeaders(['apikey' => $this->apiKey()])
            ->timeout($this->timeout())
            ->acceptJson();

        if ($query !== []) {
            $pending = $pending->withQueryParameters($query);
        }

        return match (strtolower($method)) {
            'get' => $pending->get($url),
            'post' => $pending->post($url, $body ?? []),
            'delete' => $pending->delete($url, $body ?? []),
            default => throw new RuntimeException('Unsupported HTTP method: '.$method),
        };
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.evolution.url', ''), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.evolution.api_key', '');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.evolution.timeout', 15));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractState(array $payload): ?string
    {
        $candidates = [
            $payload['instance']['state'] ?? null,
            $payload['instance']['status'] ?? null,
            $payload['state'] ?? null,
            $payload['status'] ?? null,
            $payload['connectionStatus'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function extractPairingCode(array $payload): ?string
    {
        $candidates = [
            $payload['pairingCode'] ?? null,
            $payload['code'] ?? null,
            $payload['pairing']['code'] ?? null,
            $payload['data']['pairingCode'] ?? null,
            $payload['data']['code'] ?? null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}

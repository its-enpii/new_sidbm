<?php

declare(strict_types=1);

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * WA Gateway API client for n8n webhook proxy -> Evolution API.
 *
 * @see F:\Workspace\laragon\www\sidbm\WA-GATEWAY-API.md
 */
final class WhatsappGatewayService
{
    private const KEY_ENABLED = 'whatsapp.is_enabled';
    private const KEY_PHONE = 'whatsapp.pairing_phone';

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
        $prefix = (string) config('services.wa_gateway.instance_prefix', 'app-sidbm');
        if (! str_starts_with($prefix, 'app-')) {
            $prefix = 'app-'.ltrim($prefix, '-');
        }
        $prefix = preg_replace('/[^a-zA-Z0-9_-]/', '', $prefix) ?: 'app-sidbm';

        $tenantId = $this->context->id();

        return $prefix.'-'.$tenantId;
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
     * Endpoint 1: Create Instance via n8n POST /create-instance
     *
     * @return array{success:bool,instance:string,qr:?string,pairingCode:?string,state:string,message:string}
     */
    public function createInstance(?string $lokasi = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'instance' => $this->getInstance(),
                'qr' => null,
                'pairingCode' => null,
                'state' => 'unconfigured',
                'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum dikonfigurasi.',
            ];
        }

        $instance = $this->getInstance();
        $lokasiCode = $lokasi ?? (string) $this->context->id();

        try {
            $response = $this->request('post', '/create-instance', [
                'instance' => $instance,
                'lokasi' => $lokasiCode,
            ]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp createInstance failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'instance' => $instance,
                'qr' => null,
                'pairingCode' => null,
                'state' => 'network_error',
                'message' => 'Gagal menghubungi server gateway: '.$e->getMessage(),
            ];
        }

        $payload = $response->json() ?? [];
        $qr = $payload['qr'] ?? $payload['instance']['qr'] ?? null;
        $state = $payload['state'] ?? $payload['instance']['status'] ?? ($response->successful() ? 'connecting' : 'failed');

        return [
            'success' => $response->successful() && (bool) ($payload['success'] ?? true),
            'instance' => $instance,
            'qr' => is_string($qr) && $qr !== '' ? $qr : null,
            'pairingCode' => $payload['pairingCode'] ?? null,
            'state' => (string) $state,
            'message' => (string) ($payload['message'] ?? ($response->successful() ? 'Instance berhasil dibuat.' : 'Gagal membuat instance.')),
        ];
    }

    /**
     * Endpoint 2: Get Instance State via n8n GET /instance-state?instance=...
     *
     * @return array{success:bool,status:string,state:?string,qr:?string,instance:string,message:string,payload?:array<string,mixed>}
     */
    public function connectionState(): array
    {
        if (! $this->isConfigured()) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'state' => null,
                'qr' => null,
                'instance' => $this->getInstance(),
                'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum diisi.',
            ];
        }

        $instance = $this->getInstance();

        try {
            $response = $this->request('get', '/instance-state', query: ['instance' => $instance]);
        } catch (\Throwable $e) {
            Log::warning('WhatsApp connectionState failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'status' => 'network_error',
                'state' => null,
                'qr' => null,
                'instance' => $instance,
                'message' => 'Gagal menghubungi server: '.$e->getMessage(),
            ];
        }

        $payload = $response->json() ?? [];
        $state = $this->extractState($payload);
        $qr = $payload['qr'] ?? $payload['instance']['qr'] ?? null;

        if ($response->status() === 404) {
            return [
                'success' => true,
                'status' => 'missing',
                'state' => 'missing',
                'qr' => null,
                'instance' => $instance,
                'message' => 'Instance belum dibuat. Klik "Buat Instance" untuk memulainya.',
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        if (! $response->successful()) {
            return [
                'success' => false,
                'status' => 'http_'.$response->status(),
                'state' => $state,
                'qr' => is_string($qr) ? $qr : null,
                'instance' => $instance,
                'message' => 'Server gateway mengembalikan status '.$response->status().'.',
                'payload' => is_array($payload) ? $payload : [],
            ];
        }

        $open = in_array(strtolower((string) $state), ['open', 'connected'], true);

        return [
            'success' => true,
            'status' => $open ? 'open' : 'connecting',
            'state' => $state ?: ($open ? 'open' : 'connecting'),
            'qr' => $open ? null : (is_string($qr) ? $qr : null),
            'instance' => $instance,
            'message' => $open ? 'WhatsApp terhubung.' : 'WhatsApp belum terhubung (scan QR).',
            'payload' => is_array($payload) ? $payload : [],
        ];
    }

    /**
     * Endpoint 3: Delete Instance via n8n DELETE /delete-instance?instance=...
     *
     * @return array{success:bool,deleted:bool,message:string}
     */
    public function deleteInstance(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'deleted' => false, 'message' => 'Gateway belum dikonfigurasi.'];
        }

        $instance = $this->getInstance();

        try {
            $response = $this->request('delete', '/delete-instance', query: ['instance' => $instance]);
            $payload = $response->json() ?? [];

            return [
                'success' => $response->successful(),
                'deleted' => $response->successful(),
                'message' => (string) ($payload['message'] ?? ($response->successful() ? 'Session WhatsApp berhasil dihapus.' : 'Gagal menghapus session.')),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp deleteInstance failed', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'deleted' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Endpoint 4: Send Single Message via n8n POST /send-message
     *
     * @return array{success:bool,message:string,data?:mixed}
     */
    public function sendText(string $phone, string $message, ?string $instance = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'WA_GATEWAY_BASE / WA_GATEWAY_API_KEY belum diisi di environment.'];
        }

        if (! $this->isEnabled()) {
            return ['success' => false, 'message' => 'WhatsApp gateway belum diaktifkan di pengaturan.'];
        }

        $normalized = $this->normalizePhone($phone);
        if ($normalized === '') {
            return ['success' => false, 'message' => 'Nomor penerima kosong/tidak valid.'];
        }

        $message = trim($message);
        if ($message === '') {
            return ['success' => false, 'message' => 'Isi pesan kosong.'];
        }

        $targetInstance = $instance ?? $this->getInstance();

        try {
            $response = $this->request('post', '/send-message', [
                'instance' => $targetInstance,
                'number' => $normalized,
                'text' => $message,
            ]);

            $payload = $response->json() ?? [];

            if ($response->successful() && ($payload['success'] ?? true)) {
                return ['success' => true, 'message' => 'Pesan terkirim.', 'data' => $payload];
            }

            Log::warning('WhatsApp sendText failed', [
                'tenant_id' => $this->context->id(),
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return [
                'success' => false,
                'message' => (string) ($payload['message'] ?? 'Gagal mengirim pesan: HTTP '.$response->status()),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp sendText exception', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'message' => 'Exception: '.$e->getMessage()];
        }
    }

    /**
     * Endpoint 5: Send Bulk Messages via n8n POST /send-messages
     *
     * @param list<array{number:string,text:string}> $messages
     * @return array{success:bool,count:int,message:string}
     */
    public function sendMessages(array $messages, ?string $instance = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'count' => 0, 'message' => 'Gateway WhatsApp belum dikonfigurasi.'];
        }

        if (! $this->isEnabled()) {
            return ['success' => false, 'count' => 0, 'message' => 'WhatsApp gateway belum diaktifkan.'];
        }

        $formatted = [];
        foreach ($messages as $item) {
            $num = $this->normalizePhone((string) ($item['number'] ?? ''));
            $txt = trim((string) ($item['text'] ?? ''));
            if ($num !== '' && $txt !== '') {
                $formatted[] = ['number' => $num, 'text' => $txt];
            }
        }

        if ($formatted === []) {
            return ['success' => false, 'count' => 0, 'message' => 'Daftar pesan kosong atau tidak valid.'];
        }

        $targetInstance = $instance ?? $this->getInstance();

        try {
            $response = $this->request('post', '/send-messages', [
                'instance' => $targetInstance,
                'messages' => $formatted,
            ]);

            $payload = $response->json() ?? [];

            if ($response->successful() && ($payload['success'] ?? true)) {
                return [
                    'success' => true,
                    'count' => count($formatted),
                    'message' => 'Berhasil mengirim '.count($formatted).' pesan.',
                ];
            }

            return [
                'success' => false,
                'count' => 0,
                'message' => (string) ($payload['message'] ?? 'Gagal mengirim pesan massal.'),
            ];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp sendMessages exception', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'count' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * Endpoint 6: Get History Messages via n8n GET /history-message?instance=...
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function historyMessage(?string $instance = null): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'data' => [], 'message' => 'Gateway belum dikonfigurasi.'];
        }

        $targetInstance = $instance ?? $this->getInstance();

        try {
            $response = $this->request('get', '/history-message', query: ['instance' => $targetInstance]);

            return $response->json() ?? ['success' => false, 'data' => []];
        } catch (\Throwable $e) {
            Log::warning('WhatsApp historyMessage exception', [
                'tenant_id' => $this->context->id(),
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'data' => [], 'message' => $e->getMessage()];
        }
    }

    /**
     * Normalize to digits only; convert 08... -> 62...
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

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     */
    private function request(string $method, string $path, ?array $body = null, array $query = []): Response
    {
        $url = $this->baseUrl().'/'.ltrim($path, '/');
        $apiKey = $this->apiKey();
        $basicToken = base64_encode($apiKey);

        $pending = $this->http
            ->withToken($basicToken, 'Basic')
            ->withHeaders([
                'Content-Type' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            ])
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
        return rtrim((string) config('services.wa_gateway.base_url', ''), '/');
    }

    private function apiKey(): string
    {
        return (string) config('services.wa_gateway.api_key', '');
    }

    private function timeout(): int
    {
        return max(5, (int) config('services.wa_gateway.timeout', 15));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractState(array $payload): ?string
    {
        $candidates = [
            $payload['instance']['status'] ?? null,
            $payload['instance']['state'] ?? null,
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
}

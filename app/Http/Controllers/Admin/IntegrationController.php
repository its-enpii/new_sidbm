<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Settings\OrchestratorRequest;
use App\Services\Assistant\OrchestratorClient;
use App\Services\PlatformSettingService;
use App\Support\AssistantSettingsResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class IntegrationController
{
    public function index(PlatformSettingService $settings, OrchestratorClient $client): Response
    {
        $baseUrl = (string) ($settings->get('assistant.orchestrator_base_url')
            ?: (config('assistant.base_url') ?? ''));
        $publicUrl = (string) ($settings->get('assistant.orchestrator_public_url')
            ?: $baseUrl);
        $adapterUrl = (string) ($settings->get('assistant.adapter_base_url')
            ?: (config('assistant.adapter_base_url') ?? ''));
        $widgetRaw = $settings->get('assistant.widget_enabled');
        $widgetEnabled = $widgetRaw === null
            ? (bool) config('assistant.widget_enabled', false)
            : in_array(strtolower((string) $widgetRaw), ['1', 'true', 'yes', 'on'], true);
        $skewRaw = $settings->get('assistant.signature_max_skew_ms');
        $skewMs = ($skewRaw !== null && $skewRaw !== '' && ctype_digit((string) $skewRaw))
            ? (int) $skewRaw
            : (int) config('assistant.signature_max_skew_ms', 300_000);
        $hasSecret = $settings->getEncrypted('assistant.shared_secret', '') !== '';

        return Inertia::render('Admin/Integration', [
            'orchestrator' => [
                'orchestrator_base_url' => $baseUrl,
                'orchestrator_public_url' => $publicUrl,
                'adapter_base_url' => $adapterUrl,
                'widget_enabled' => $widgetEnabled,
                'signature_max_skew_ms' => $skewMs,
                'has_secret' => $hasSecret,
                'configured' => $client->isConfigured(),
            ],
            'defaults' => [
                'orchestrator_base_url' => (string) (config('assistant.base_url') ?? ''),
                'orchestrator_public_url' => (string) (config('assistant.public_url') ?? ''),
                'adapter_base_url' => (string) (config('assistant.adapter_base_url') ?? ''),
                'widget_enabled' => (bool) config('assistant.widget_enabled', false),
                'signature_max_skew_ms' => (int) config('assistant.signature_max_skew_ms', 300_000),
            ],
        ]);
    }

    public function update(OrchestratorRequest $request, PlatformSettingService $settings): RedirectResponse
    {
        $data = $request->validated();

        $settings->set('assistant.orchestrator_base_url', $data['orchestrator_base_url']);
        if (! empty($data['orchestrator_public_url'])) {
            $settings->set('assistant.orchestrator_public_url', $data['orchestrator_public_url']);
        } else {
            $settings->set('assistant.orchestrator_public_url', null);
        }
        if (! empty($data['adapter_base_url'])) {
            $settings->set('assistant.adapter_base_url', $data['adapter_base_url']);
        } else {
            $settings->set('assistant.adapter_base_url', null);
        }
        if (! empty($data['shared_secret'])) {
            $settings->setEncrypted('assistant.shared_secret', $data['shared_secret']);
        }
        $settings->set('assistant.signature_max_skew_ms', (string) $data['signature_max_skew_ms']);
        $settings->set('assistant.widget_enabled', $data['widget_enabled'] ? '1' : '0');

        $settings->flush();

        return back()->with('success', ['message' => 'Pengaturan orchestrator berhasil disimpan.', 'tab' => 'orchestrator']);
    }

    public function test(OrchestratorClient $client): JsonResponse
    {
        $started = microtime(true);
        try {
            if (! $client->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'status' => 'unconfigured',
                    'message' => 'Isi URL server-to-server + shared secret terlebih dahulu.',
                    'latency_ms' => null,
                ]);
            }

            $issued = $client->issueSessionToken(
                externalUserId: 'probe-'.bin2hex(random_bytes(4)),
                displayName: 'Health probe',
            );

            return response()->json([
                'success' => true,
                'status' => 'connected',
                'message' => 'Terhubung ke orchestrator.',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'expires_at' => $issued['expires_at'] ?? null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }

    public function chat(Request $request, OrchestratorClient $client): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'string', 'max:64'],
            'persona_slug' => ['nullable', 'string', 'max:64'],
        ]);

        return response()->stream(function () use ($client, $data): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }

            try {
                foreach ($client->streamChat(
                    message: $data['message'],
                    conversationId: $data['conversation_id'] ?? null,
                    personaSlug: $data['persona_slug'] ?? null,
                ) as $event) {
                    echo 'event: '.$event['event']."\n";
                    foreach (explode("\n", $event['data']) as $line) {
                        echo 'data: '.$line."\n";
                    }
                    echo "\n";
                    @ob_flush();
                    @flush();
                }
            } catch (\Throwable $e) {
                echo 'event: error'."\n";
                echo 'data: '.json_encode(['message' => $e->getMessage()])."\n\n";
                @ob_flush();
                @flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'file', 'max:20480', 'mimetypes:text/plain,text/markdown,text/html,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);

        $base = rtrim(AssistantSettingsResolver::orchestratorBaseUrl(), '/');
        $secret = AssistantSettingsResolver::sharedSecret();
        if ($base === '' || $secret === '') {
            return response()->json([
                'ok' => false,
                'message' => 'URL server / shared secret belum diisi.',
            ], 422);
        }

        // Resolve tenant id via /api/v1/personas (authenticated by shared secret → tenant key).
        $probe = Http::timeout(15)
            ->withToken($secret)
            ->acceptJson()
            ->get($base.'/api/v1/personas');

        $tenantId = null;
        if ($probe->successful()) {
            $payload = $probe->json();
            $tenantId = $payload['tenant']['id'] ?? null;
        }

        if (! $tenantId) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak bisa menentukan tenant. Pastikan shared secret valid.',
            ], 502);
        }

        $response = Http::timeout(60)
            ->withToken($secret)
            ->acceptJson()
            ->attach(
                'file',
                file_get_contents($data['file']->getRealPath()),
                $data['file']->getClientOriginalName(),
            )
            ->post($base.'/admin/tenants/'.$tenantId.'/knowledge/upload', [
                'title' => $data['title'] ?? null,
            ]);

        if (! $response->successful()) {
            return response()->json([
                'ok' => false,
                'message' => 'HTTP '.$response->status().': '.$response->body(),
            ], $response->status());
        }

        return response()->json(['ok' => true]);
    }
}

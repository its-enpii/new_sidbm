<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Server-to-server client for the orchestrator session endpoint.
 *
 * The host (tenant app) calls this when a browser asks for a chat session.
 */
final class OrchestratorClient
{
    public function isConfigured(): bool
    {
        return (string) config('assistant.base_url') !== ''
            && (string) config('assistant.shared_secret') !== '';
    }

    /**
     * @return array{
     *     session_token: string,
     *     expires_at: string,
     *     public_base_url: string,
     *     conversation_id: ?string,
     *     persona: ?array{id?: ?string, slug?: ?string, name?: ?string},
     *     adapter: ?array{base_url?: ?string, tenant_code?: ?string}
     * }
     */
    public function issueSessionToken(
        string $externalUserId,
        ?string $displayName = null,
        ?string $tenantCode = null,
        ?string $personaSlug = null,
        ?string $adapterBaseUrl = null,
    ): array {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Assistant orchestrator is not configured.');
        }

        $base = rtrim((string) ($adapterBaseUrl
            ?: config('assistant.adapter_base_url')
            ?: config('app.url')), '/');

        if ($base === '') {
            throw new RuntimeException('adapter_base_url missing — set APP_URL or ASSISTANT_ADAPTER_BASE_URL.');
        }

        $payload = array_filter([
            'external_user_id' => $externalUserId,
            'display_name' => $displayName,
            'channel' => 'web',
            'persona_slug' => $personaSlug,
            'adapter_base_url' => $base,
            'adapter_tenant_code' => $tenantCode,
        ], static fn ($v) => $v !== null && $v !== '');

        $response = Http::timeout((int) config('assistant.timeout', 10))
            ->withToken((string) config('assistant.shared_secret'))
            ->acceptJson()
            ->post(rtrim((string) config('assistant.base_url'), '/').'/api/v1/sessions', $payload);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to issue assistant session: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        if (! isset($json['session_token']) || ! is_string($json['session_token']) || $json['session_token'] === '') {
            throw new RuntimeException('Session response missing session_token.');
        }

        $persona = null;
        if (isset($json['persona']) && is_array($json['persona'])) {
            $persona = [
                'id' => isset($json['persona']['id']) ? (string) $json['persona']['id'] : null,
                'slug' => isset($json['persona']['slug']) ? (string) $json['persona']['slug'] : null,
                'name' => isset($json['persona']['name']) ? (string) $json['persona']['name'] : null,
            ];
        }

        $adapter = null;
        if (isset($json['adapter']) && is_array($json['adapter'])) {
            $adapter = [
                'base_url' => isset($json['adapter']['base_url']) ? (string) $json['adapter']['base_url'] : $base,
                'tenant_code' => isset($json['adapter']['tenant_code']) ? (string) $json['adapter']['tenant_code'] : $tenantCode,
            ];
        }

        return [
            'session_token' => $json['session_token'],
            'expires_at' => (string) ($json['expires_at'] ?? ''),
            'public_base_url' => (string) ($json['public_base_url'] ?? $this->publicBaseUrl()),
            'conversation_id' => isset($json['conversation_id']) && is_string($json['conversation_id'])
                ? $json['conversation_id']
                : null,
            'persona' => $persona,
            'adapter' => $adapter,
        ];
    }

    public function publicBaseUrl(): string
    {
        $public = rtrim((string) config('assistant.public_url', ''), '/');
        if ($public !== '') {
            return $public;
        }

        return rtrim((string) config('assistant.base_url', ''), '/');
    }
}
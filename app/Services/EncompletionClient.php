<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class EncompletionClient
{
    public function isConfigured(): bool
    {
        return config('encompletion.base_url') !== ''
            && config('encompletion.tenant_api_key') !== '';
    }

    /**
     * @return array{embed_token:string,expires_at:string,tenant_id:mixed,external_user_id:string,persona_config:?array}
     */
    public function issueEmbedToken(string $externalUserId): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Encompletion is not configured.');
        }

        $response = Http::timeout((int) config('encompletion.timeout', 10))
            ->withToken((string) config('encompletion.tenant_api_key'))
            ->acceptJson()
            ->post(rtrim((string) config('encompletion.base_url'), '/').'/api/embed/token', [
                'external_user_id' => $externalUserId,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Failed to issue embed token: HTTP '.$response->status().' '.$response->body()
            );
        }

        /** @var array{embed_token?:string,expires_at?:string,tenant_id?:mixed,external_user_id?:string,persona_config?:?array} $json */
        $json = $response->json() ?? [];
        if (! isset($json['embed_token']) || ! is_string($json['embed_token'])) {
            throw new RuntimeException('Embed token response missing embed_token.');
        }

        return [
            'embed_token' => $json['embed_token'],
            'expires_at' => (string) ($json['expires_at'] ?? ''),
            'tenant_id' => $json['tenant_id'] ?? null,
            'external_user_id' => (string) ($json['external_user_id'] ?? $externalUserId),
            'persona_config' => is_array($json['persona_config'] ?? null) ? $json['persona_config'] : null,
        ];
    }

    public function publicBaseUrl(): string
    {
        $public = rtrim((string) config('encompletion.public_url', ''), '/');
        if ($public !== '') {
            return $public;
        }

        return rtrim((string) config('encompletion.base_url', ''), '/');
    }

    public function widgetScriptUrl(): string
    {
        return $this->publicBaseUrl().'/embed/widget.js';
    }
}


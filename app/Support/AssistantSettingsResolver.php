<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\PlatformSettingService;

/**
 * Single source of truth for orchestrator-related runtime config.
 *
 * Platform-level settings (set by superadmin via /admin/integrations) take
 * precedence; env vars are the fallback when no admin override is stored.
 *
 * Used by:
 *   - VerifyOrchestratorSignature middleware (inbound signature check)
 *   - HandleInertiaRequests (widget visibility share to frontend)
 *   - OrchestratorClient (outbound session mint + tool callback URLs)
 */
final class AssistantSettingsResolver
{
    /**
     * @return array{base_url: string, public_url: string, adapter_base_url: string, widget_enabled: bool, signature_max_skew_ms: int}
     */
    public static function all(): array
    {
        return [
            'base_url' => self::orchestratorBaseUrl(),
            'public_url' => self::orchestratorPublicUrl(),
            'adapter_base_url' => self::adapterBaseUrl(),
            'widget_enabled' => self::widgetEnabled(),
            'signature_max_skew_ms' => self::signatureMaxSkewMs(),
        ];
    }

    public static function orchestratorBaseUrl(): string
    {
        $stored = self::platformSetting('assistant.orchestrator_base_url');
        if (is_string($stored) && $stored !== '') {
            return self::rtrimSlash($stored);
        }

        return self::rtrimSlash((string) (config('assistant.base_url') ?? ''));
    }

    public static function orchestratorPublicUrl(): string
    {
        $stored = self::platformSetting('assistant.orchestrator_public_url');
        if (is_string($stored) && $stored !== '') {
            return self::rtrimSlash($stored);
        }

        return self::orchestratorBaseUrl();
    }

    public static function adapterBaseUrl(): string
    {
        $stored = self::platformSetting('assistant.adapter_base_url');
        if (is_string($stored) && $stored !== '') {
            return self::rtrimSlash($stored);
        }

        return self::rtrimSlash((string) (config('assistant.adapter_base_url') ?? config('app.url') ?? ''));
    }

    public static function sharedSecret(): string
    {
        $stored = self::encryptedPlatformSetting('assistant.shared_secret');
        if ($stored !== '') {
            return $stored;
        }

        return (string) config('assistant.shared_secret', '');
    }

    public static function widgetEnabled(): bool
    {
        $stored = self::platformSetting('assistant.widget_enabled');
        if ($stored === null || $stored === '') {
            return (bool) config('assistant.widget_enabled', false);
        }

        return in_array(strtolower((string) $stored), ['1', 'true', 'yes', 'on'], true);
    }

    public static function signatureMaxSkewMs(): int
    {
        $stored = self::platformSetting('assistant.signature_max_skew_ms');
        if ($stored !== null && $stored !== '' && ctype_digit((string) $stored)) {
            return (int) $stored;
        }

        return (int) config('assistant.signature_max_skew_ms', 300_000);
    }

    private static function platformSetting(string $key): mixed
    {
        try {
            return app(PlatformSettingService::class)->get($key);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function encryptedPlatformSetting(string $key): string
    {
        try {
            $value = app(PlatformSettingService::class)->getEncrypted($key, '');

            return is_string($value) ? $value : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private static function rtrimSlash(string $value): string
    {
        return rtrim($value, '/');
    }
}

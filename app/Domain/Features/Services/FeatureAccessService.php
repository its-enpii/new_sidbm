<?php

declare(strict_types=1);

namespace App\Domain\Features\Services;

use App\Models\Platform\Tenant;
use App\Services\PlatformSettingService;
use App\Tenancy\TenantContext;

/**
 * Single source of truth for feature availability across platform and tenants.
 */
final class FeatureAccessService
{
    /** @var array<string, bool> */
    private array $cache = [];

    public function __construct(
        private readonly PlatformSettingService $platformSettings,
        private readonly TenantContext $tenantContext,
    ) {}

    public function aiEnabled(?int $tenantRowId = null): bool
    {
        return $this->enabled('ai', $tenantRowId);
    }

    public function enabled(string $feature, ?int $tenantRowId = null): bool
    {
        $tenantIdKey = $tenantRowId ?? ($this->tenantContext->isInitialized() ? $this->tenantContext->id() : 'none');
        $cacheKey = "{$feature}|{$tenantIdKey}";

        if (array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        return $this->cache[$cacheKey] = match ($feature) {
            'ai' => $this->resolveAi($tenantRowId),
            default => false,
        };
    }

    public function flush(): void
    {
        $this->cache = [];
    }

    /**
     * Resolves AI feature flag based on strict hierarchy:
     * 1. Master kill-switch in platform_settings ('ai.enabled') === false -> false
     * 2. Explicit tenant override in tenants.ai_enabled: true -> true, false -> false
     * 3. Default (training mode -> true, non-training -> false)
     * 4. No tenant / invalid tenant -> false (fail-closed)
     */
    private function resolveAi(?int $tenantRowId): bool
    {
        // 1. Master platform kill-switch (default true)
        $masterEnabled = (bool) $this->platformSettings->get('ai.enabled', true);
        if (! $masterEnabled) {
            return false;
        }

        $tenant = $this->resolveTenant($tenantRowId);
        if ($tenant === null) {
            return false;
        }

        // 2. Explicit tenant override (nullable tri-state)
        $override = $tenant->aiEnabledExplicit();
        if ($override !== null) {
            return $override;
        }

        // 3. Plan / Training default hook
        return $this->planDefault($tenant);
    }

    /**
     * Future billing integration hook (plans / add-ons).
     *
     * TODO: Once subscription add-on billing is active, resolve AI entitlement
     * from active tenant subscription or purchased add-on packages.
     */
    private function planDefault(Tenant $tenant): bool
    {
        return $tenant->isTraining();
    }

    private function resolveTenant(?int $tenantRowId): ?Tenant
    {
        if ($tenantRowId !== null) {
            return Tenant::query()->whereKey($tenantRowId)->first();
        }

        if ($this->tenantContext->isInitialized()) {
            return $this->tenantContext->tenant();
        }

        return null;
    }
}

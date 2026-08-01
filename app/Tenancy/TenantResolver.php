<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Platform\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class TenantResolver
{
    public function resolveByCode(string $code, ?Authenticatable $user = null): Tenant
    {
        $tenant = Tenant::query()->with(['placement.shard'])
            ->where('code', $code)->whereIn('status', ['active', 'read_only'])->first();

        if ($tenant === null) {
            throw (new ModelNotFoundException())->setModel(Tenant::class, [$code]);
        }

        return $this->validateTenantAccess($tenant, $user);
    }

    public function resolveByHost(string $host, ?Authenticatable $user = null): Tenant
    {
        $normalizedHost = strtolower(trim(explode(':', $host)[0]));
        $localTenant = (string) config('tenancy.local_tenant', '');
        $tenant = Tenant::query()->with(['placement.shard'])->whereIn('status', ['active', 'read_only'])
            ->get()->first(fn (Tenant $candidate): bool => $this->candidateMatchesHost($candidate, $normalizedHost));

        // host.docker.internal: tool callbacks from orchestrator container → host SIDBM
        $localHosts = ['localhost', '127.0.0.1', '::1', 'host.docker.internal'];
        if ($tenant === null && $localTenant !== '' && in_array($normalizedHost, $localHosts, true)) {
            $tenant = Tenant::query()->with(['placement.shard'])->where('code', $localTenant)
                ->whereIn('status', ['active', 'read_only'])->first();
        }

        if ($tenant === null) {
            throw new AccessDeniedHttpException('Tenant could not be resolved from the request host.');
        }

        return $this->validateTenantAccess($tenant, $user);
    }

    public function resolveForUser(Authenticatable $user): Tenant
    {
        $tenant = Tenant::query()->with(['placement.shard'])->find($user->getAttribute('tenant_id'));

        if ($tenant === null) {
            throw new AccessDeniedHttpException('User has no valid tenant assignment.');
        }

        return $this->validateTenantAccess($tenant, $user);
    }

    public function resolveById(int $tenantId): Tenant
    {
        return Tenant::query()->with(['placement.shard'])->whereKey($tenantId)
            ->whereIn('status', ['active', 'read_only', 'migrating'])->firstOrFail();
    }

    private function validateTenantAccess(Tenant $tenant, ?Authenticatable $user): Tenant
    {
        if ($tenant->placement === null || $tenant->placement->shard === null) {
            throw new AccessDeniedHttpException('Tenant has no active shard placement.');
        }

        if ($user === null) {
            return $tenant;
        }

        $assignedTenantId = $user->getAttribute('tenant_id');
        $hasMembership = $tenant->memberships()->where('user_id', $user->getAuthIdentifier())
            ->where('status', 'active')->exists();

        if (($assignedTenantId !== null && (int) $assignedTenantId !== (int) $tenant->row_id) || ! $hasMembership) {
            throw new AccessDeniedHttpException('You do not have access to this tenant.');
        }

        if ($assignedTenantId === null) {
            $user->forceFill(['tenant_id' => $tenant->row_id])->saveQuietly();
        }

        return $tenant;
    }

    private function candidateMatchesHost(Tenant $tenant, string $host): bool
    {
        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
        $domains = is_array($domains) ? $domains : [$domains];

        return collect($domains)->contains(fn ($domain): bool => strtolower((string) $domain) === $host);
    }
}

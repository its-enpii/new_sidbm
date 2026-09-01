<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Desktop;

use App\Domain\Sync\Services\DesktopPushApplyService;
use App\Domain\Sync\Services\TenantSnapshotService;
use App\Models\Platform\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DesktopSyncController
{
    public function __construct(
        private readonly TenantSnapshotService $snapshotService,
        private readonly DesktopPushApplyService $pushService,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Full Snapshot Export for Desktop SQLite Initialization.
     */
    public function snapshot(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $payload = $this->snapshotService->export($targetTenant, null);

        return response()->json([
            'status' => 'success',
            'message' => 'Full tenant snapshot generated successfully.',
            ...$payload,
        ]);
    }

    public function push(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $validated = $request->validate([
            'mutations' => ['required', 'array', 'max:200'],
            'mutations.*.mutation_uuid' => ['required', 'uuid'],
            'mutations.*.table_name' => ['required', 'string', 'max:100'],
            'mutations.*.operation' => ['required', 'string', 'in:insert,update,delete'],
            'mutations.*.row_public_id' => ['required', 'integer'],
            'mutations.*.payload' => ['required', 'array'],
            'mutations.*.client_updated_at' => ['nullable', 'date'],
            'last_pulled_at' => ['nullable', 'date'],
        ]);

        $result = $this->pushService->apply(
            $targetTenant,
            $validated['mutations'],
            isset($validated['last_pulled_at']) ? (string) $validated['last_pulled_at'] : null,
        );

        return response()->json([
            'status' => 'success',
            ...$result,
        ]);
    }

    /**
     * Incremental / Delta Snapshot Export for Desktop Synchronization.
     */
    public function delta(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        $since = (string) $request->query('since', '');
        if (trim($since) === '') {
            return response()->json([
                'status' => 'error',
                'message' => 'The [since] query parameter (ISO8601 timestamp) is required for delta synchronization.',
            ], 422);
        }

        $payload = $this->snapshotService->export($targetTenant, $since);

        return response()->json([
            'status' => 'success',
            'message' => 'Delta tenant snapshot generated successfully.',
            ...$payload,
        ]);
    }

    /**
     * Quick status check and metadata for desktop app.
     */
    public function status(Request $request, ?string $tenant = null): JsonResponse
    {
        $targetTenant = $this->resolveTenant($request, $tenant);
        if ($targetTenant === null) {
            return $this->tenantNotFoundResponse();
        }

        return response()->json([
            'status' => 'success',
            'server_time' => now()->toIso8601String(),
            'app_name' => config('app.name', 'SIDBM Next'),
            'app_version' => '1.0.0',
            'tenant' => [
                'id' => (int) $targetTenant->row_id,
                'code' => (string) $targetTenant->code,
                'name' => (string) $targetTenant->name,
                'status' => (string) $targetTenant->status,
                'district_code' => $targetTenant->district_code,
                'regency_code' => $targetTenant->regency_code,
                'regency_name' => $targetTenant->regency_name,
                'province_code' => $targetTenant->province_code,
                'shard' => $targetTenant->placement?->shard?->code,
            ],
        ]);
    }

    private function resolveTenant(Request $request, ?string $routeTenant): ?Tenant
    {
        $identifier = $routeTenant
            ?? $request->header('X-Tenant-Code')
            ?? $request->query('tenant')
            ?? $request->query('tenant_id');

        if (is_string($identifier) && trim($identifier) !== '') {
            $trimmed = trim($identifier);
            $query = Tenant::query()->with(['placement.shard'])->whereIn('status', ['active', 'read_only']);

            return is_numeric($trimmed)
                ? $query->where('row_id', (int) $trimmed)->first()
                : $query->where('code', $trimmed)->first();
        }

        if ($this->tenantContext->isInitialized()) {
            return $this->tenantContext->tenant();
        }

        $user = $request->user();
        if ($user !== null && $user->getAttribute('tenant_id') !== null) {
            $tenantId = (int) $user->getAttribute('tenant_id');

            return Tenant::query()->with(['placement.shard'])->where('row_id', $tenantId)->first();
        }

        return null;
    }

    private function tenantNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Tenant not found. Please provide a valid tenant code or ID.',
        ], 404);
    }
}

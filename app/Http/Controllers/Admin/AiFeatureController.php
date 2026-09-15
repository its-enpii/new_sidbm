<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Features\Services\FeatureAccessService;
use App\Http\Requests\Admin\AiFeatureRequest;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Services\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AiFeatureController
{
    public function index(Request $request, PlatformSettingService $platformSettings, FeatureAccessService $featureAccess): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = in_array($request->query('sort'), ['name', 'code', 'district_code', 'is_training_mode'], true)
            ? (string) $request->query('sort')
            : 'row_id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $tenants = Tenant::query()
            ->with(['activeSubscription.plan:row_id,code,name'])
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('district_code', 'like', "%{$search}%")))
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Tenant $tenant): array => [
                'row_id' => $tenant->row_id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'district_code' => $tenant->district_code,
                'is_training_mode' => (bool) ($tenant->is_training_mode ?? false),
                'ai_enabled' => $tenant->ai_enabled === null ? 'inherit' : ($tenant->ai_enabled ? 'on' : 'off'),
                'effective_ai_enabled' => $featureAccess->aiEnabled((int) $tenant->row_id),
                'plan' => $tenant->activeSubscription?->plan?->only(['code', 'name']),
            ]);

        $globalAiEnabled = (bool) $platformSettings->get('ai.enabled', true);

        return Inertia::render('Admin/Features/Index', compact(
            'tenants',
            'search',
            'perPage',
            'sort',
            'direction',
            'globalAiEnabled'
        ));
    }

    public function updateGlobal(Request $request, PlatformSettingService $platformSettings, FeatureAccessService $featureAccess, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $new = (bool) $validated['enabled'];
        $old = (bool) $platformSettings->get('ai.enabled', true);

        $platformSettings->set('ai.enabled', $new, 'bool');
        $featureAccess->flush();

        $changes = AuditLogger::diff(['ai.enabled' => $old], ['ai.enabled' => $new]);

        $audit->record(
            'platform_setting.ai.update',
            null,
            'PlatformSetting',
            0,
            'Kunci global AI diubah menjadi: '.($new ? 'Aktif' : 'Nonaktif'),
            ['changes' => $changes],
        );

        return back()->with('success', 'Kunci global fitur AI berhasil diperbarui.');
    }

    public function updateTenant(AiFeatureRequest $request, Tenant $tenant, FeatureAccessService $featureAccess, AuditLogger $audit): RedirectResponse
    {
        $valueStr = (string) $request->validated('value');
        $newVal = match ($valueStr) {
            'on' => true,
            'off' => false,
            'inherit' => null,
        };

        $oldVal = $tenant->ai_enabled;
        $changes = AuditLogger::diff(['ai_enabled' => $oldVal], ['ai_enabled' => $newVal]);

        $tenant->forceFill(['ai_enabled' => $newVal])->save();
        $featureAccess->flush();

        $audit->record(
            'tenant.ai_feature.update',
            $tenant,
            Tenant::class,
            $tenant->row_id,
            "Status fitur AI tenant [{$tenant->code}] diubah menjadi [{$valueStr}].",
            ['changes' => $changes],
        );

        return back()->with('success', "Status fitur AI untuk tenant [{$tenant->name}] berhasil diperbarui.");
    }

    public function bulk(AiFeatureRequest $request, FeatureAccessService $featureAccess, AuditLogger $audit): RedirectResponse
    {
        $valueStr = (string) $request->validated('value');
        $newVal = match ($valueStr) {
            'on' => true,
            'off' => false,
            'inherit' => null,
        };

        /** @var list<int> $tenantIds */
        $tenantIds = array_map('intval', (array) $request->validated('tenant_ids'));

        Tenant::query()->whereIn('row_id', $tenantIds)->update(['ai_enabled' => $newVal]);
        $featureAccess->flush();

        $audit->record(
            'tenant.ai_feature.bulk',
            null,
            Tenant::class,
            0,
            'Status fitur AI diubah massal menjadi ['.$valueStr.'] untuk '.count($tenantIds).' tenant.',
            ['tenant_ids' => $tenantIds, 'value' => $valueStr],
        );

        return back()->with('success', 'Status fitur AI untuk '.count($tenantIds).' tenant terpilih berhasil diperbarui.');
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [15, 30, 50, 100], true) ? $perPage : 15;
    }
}

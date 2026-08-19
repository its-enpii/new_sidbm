<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Models\Platform\Plan;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\TenantRegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class TenantController
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = $this->perPage($request->query('per_page'));
        $sort = in_array($request->query('sort'), ['name', 'code', 'status', 'provisioned_at'], true)
            ? (string) $request->query('sort')
            : 'row_id';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $tenants = Tenant::query()
            ->with(['placement.shard:row_id,code,name', 'activeSubscription.plan:row_id,code,name'])
            ->withCount('memberships')
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
                'status' => $tenant->status,
                'provisioned_at' => $tenant->provisioned_at?->toDateTimeString(),
                'memberships_count' => $tenant->memberships_count,
                'shard' => $tenant->placement?->shard?->only(['code', 'name']),
                'plan' => $tenant->activeSubscription?->plan?->only(['code', 'name']),
            ]);

        return Inertia::render('Admin/Tenants/Index', compact('tenants', 'search', 'perPage', 'sort', 'direction'));
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Tenants/Create');
    }

    public function store(StoreTenantRequest $request, TenantRegistrationService $registration): RedirectResponse
    {
        $tenant = $registration->register($request->validated());

        return to_route('admin.tenants.show', $tenant)->with('success', "Tenant [{$tenant->name}] berhasil didaftarkan.");
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->load([
            'placement.shard:row_id,code,name,database_name',
            'activeSubscription.plan',
            'subscriptions' => fn ($q) => $q->with('plan:row_id,code,name')->latest('row_id')->limit(5),
        ]);

        $users = User::query()
            ->where('tenant_id', $tenant->row_id)
            ->orderBy('name')
            ->limit(10)
            ->get(['row_id', 'name', 'username', 'email', 'status', 'last_login_at']);

        $invoices = $tenant->invoices()
            ->latest('row_id')
            ->limit(8)
            ->get(['row_id', 'number', 'status', 'amount', 'amount_paid', 'due_at', 'currency']);

        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
        $domains = is_array($domains) ? $domains : (trim((string) $domains) !== '' ? [$domains] : []);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => [
                'row_id' => $tenant->row_id,
                'public_id' => $tenant->public_id,
                'code' => $tenant->code,
                'name' => $tenant->name,
                'district_code' => $tenant->district_code,
                'status' => $tenant->status,
                'timezone' => $tenant->timezone,
                'custom_domains' => array_values(array_filter($domains)),
                'provisioned_at' => $tenant->provisioned_at?->toDateTimeString(),
                'suspended_at' => $tenant->suspended_at?->toDateTimeString(),
                'placement' => $tenant->placement ? [
                    'status' => $tenant->placement->status,
                    'shard' => $tenant->placement->shard?->only(['code', 'name', 'database_name']),
                ] : null,
                'active_subscription' => $tenant->activeSubscription ? [
                    'row_id' => $tenant->activeSubscription->row_id,
                    'status' => $tenant->activeSubscription->status,
                    'starts_at' => $tenant->activeSubscription->starts_at?->toDateString(),
                    'ends_at' => $tenant->activeSubscription->ends_at?->toDateString(),
                    'plan' => $tenant->activeSubscription->plan?->only(['row_id', 'code', 'name', 'price_amount', 'billing_period', 'currency']),
                ] : null,
            ],
            'users' => $users,
            'invoices' => $invoices,
            'plans' => Plan::query()->where('is_active', true)->orderBy('name')->get(['row_id', 'code', 'name', 'price_amount', 'billing_period', 'currency']),
        ]);
    }

    public function edit(Tenant $tenant): Response
    {
        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $domains = $metadata['domains'] ?? ($metadata['domain'] ?? []);
        $domains = is_array($domains) ? $domains : (trim((string) $domains) !== '' ? [$domains] : []);

        return Inertia::render('Admin/Tenants/Edit', [
            'tenant' => [
                ...$tenant->only(['row_id', 'code', 'name', 'district_code', 'status', 'timezone']),
                'custom_domains' => array_values(array_filter($domains)),
            ],
        ]);
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validated();
        $oldDistrict = (string) $tenant->district_code;
        $newDistrict = isset($data['district_code']) && is_string($data['district_code']) ? $data['district_code'] : null;

        $rawDomains = (array) ($data['custom_domains'] ?? []);
        $cleanDomains = [];
        foreach ($rawDomains as $d) {
            if (! is_string($d)) {
                continue;
            }
            $c = strtolower(trim($d));
            $c = preg_replace('#^https?://#', '', $c);
            $c = trim((string) explode('/', $c)[0]);
            $c = trim((string) explode(':', $c)[0]);
            if ($c !== '' && ! in_array($c, $cleanDomains, true)) {
                $cleanDomains[] = $c;
            }
        }

        $metadata = is_array($tenant->metadata) ? $tenant->metadata : [];
        $metadata['domains'] = $cleanDomains;
        unset($metadata['domain']);

        $tenant->forceFill([
            'name' => $data['name'],
            'district_code' => $newDistrict,
            'status' => $data['status'],
            'timezone' => $data['timezone'] ?? $tenant->timezone,
            'suspended_at' => $data['status'] === 'suspended' ? ($tenant->suspended_at ?? now()) : null,
            'metadata' => $metadata,
        ])->save();

        if ($newDistrict !== null && $newDistrict !== $oldDistrict) {
            try {
                app(TenantRegistrationService::class)->repair($tenant);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return to_route('admin.tenants.show', $tenant)->with('success', 'Tenant diperbarui.');
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();

        return back()->with('success', 'Tenant ditangguhkan.');
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        $tenant->forceFill(['status' => 'active', 'suspended_at' => null])->save();

        return back()->with('success', 'Tenant diaktifkan.');
    }

    public function repair(Tenant $tenant, TenantRegistrationService $registration): RedirectResponse
    {
        $result = $registration->repair($tenant);
        $coa = $result['coa'];

        return back()->with(
            'success',
            "Provision dilengkapi: COA +{$coa['inserted']} (skip {$coa['skipped']}), fiscal +{$result['fiscal_created']}."
        );
    }

    public function assignSubscription(Request $request, Tenant $tenant, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'row_id')->where('is_active', true)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'status' => ['nullable', Rule::in(['active', 'trialing'])],
        ]);

        $plan = Plan::query()->findOrFail($data['plan_id']);
        $subscriptions->assign($tenant, $plan, $data);

        return back()->with('success', 'Langganan ditetapkan.');
    }

    private function perPage(mixed $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [15, 30, 50, 100], true) ? $perPage : 15;
    }
}

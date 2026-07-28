<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Platform\Invoice;
use App\Models\Platform\Tenant;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'tenants' => Tenant::query()->count(),
                'tenants_active' => Tenant::query()->where('status', 'active')->count(),
                'users' => User::query()->whereNotNull('tenant_id')->count(),
                'invoices_open' => Invoice::query()->whereIn('status', ['issued', 'partially_paid', 'overdue'])->count(),
                'invoices_overdue' => Invoice::query()->where('status', 'overdue')->count(),
                'invoices_due_soon' => Invoice::query()
                    ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                    ->whereDate('due_at', '<=', now()->addDays(7))
                    ->count(),
            ],
            'recent_tenants' => Tenant::query()
                ->latest('row_id')
                ->limit(5)
                ->get(['row_id', 'code', 'name', 'status', 'provisioned_at']),
            'open_invoices' => Invoice::query()
                ->with('tenant:row_id,name,code')
                ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                ->orderBy('due_at')
                ->limit(8)
                ->get(['row_id', 'number', 'tenant_id', 'status', 'amount', 'amount_paid', 'due_at', 'currency']),
        ]);
    }
}

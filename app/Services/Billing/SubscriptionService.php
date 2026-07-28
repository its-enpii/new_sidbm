<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Models\Platform\Plan;
use App\Models\Platform\Subscription;
use App\Models\Platform\Tenant;
use Illuminate\Support\Facades\DB;

final readonly class SubscriptionService
{
    public function assign(Tenant $tenant, Plan $plan, array $data = []): Subscription
    {
        return DB::connection('platform')->transaction(function () use ($tenant, $plan, $data): Subscription {
            Subscription::query()
                ->where('tenant_id', $tenant->row_id)
                ->whereIn('status', ['active', 'trialing', 'past_due'])
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'auto_renew' => false,
                ]);

            return Subscription::query()->create([
                'tenant_id' => $tenant->row_id,
                'plan_id' => $plan->row_id,
                'status' => $data['status'] ?? 'active',
                'starts_at' => $data['starts_at'] ?? now()->toDateString(),
                'ends_at' => $data['ends_at'] ?? null,
                'auto_renew' => $data['auto_renew'] ?? true,
            ]);
        });
    }
}

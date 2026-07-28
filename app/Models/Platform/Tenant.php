<?php

declare(strict_types=1);

namespace App\Models\Platform;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class Tenant extends PlatformModel
{
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'provisioned_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function placement(): HasOne
    {
        return $this->hasOne(TenantPlacement::class, 'tenant_id', 'row_id')
            ->whereIn('status', ['active', 'read_only', 'switching']);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'tenant_id', 'row_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'tenant_id', 'row_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'tenant_id', 'row_id');
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'tenant_id', 'row_id')
            ->whereIn('status', ['active', 'trialing', 'past_due'])
            ->latestOfMany('row_id');
    }
}

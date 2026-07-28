<?php

declare(strict_types=1);

namespace App\Domain\Assets\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasPublicUlid;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\SoftDeletes;

final class Asset extends TenantModel
{
    use HasPublicUlid;
    use HasTenantLocalId;
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'validated_at' => 'date',
            'quantity' => 'integer',
            'unit_cost' => 'decimal:2',
            'useful_life_months' => 'integer',
        ];
    }
}

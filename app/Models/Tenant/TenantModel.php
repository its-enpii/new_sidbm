<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Tenancy\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    use BelongsToTenant;

    protected $connection = 'tenant';

    protected $primaryKey = 'row_id';

    protected $guarded = [];
}

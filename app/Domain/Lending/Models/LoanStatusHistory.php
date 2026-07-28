<?php

declare(strict_types=1);

namespace App\Domain\Lending\Models;

use App\Models\Tenant\TenantModel;
use App\Tenancy\Concerns\HasTenantLocalId;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LoanStatusHistory extends TenantModel
{
    use HasTenantLocalId;

    protected $table = 'loan_status_histories';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_row_id', 'row_id');
    }
}

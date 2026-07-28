<?php

declare(strict_types=1);

namespace App\Http\Requests\MasterData;

use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class OrganizationUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function unitId(): ?int
    {
        $unit = $this->route('unit') ?? $this->route('institution') ?? $this->route('village');

        return $unit instanceof OrganizationUnit ? $unit->row_id : (is_numeric($unit) ? (int) $unit : null);
    }

    protected function uniqueCodeRule(): Rule
    {
        return Rule::unique('organization_units', 'code')
            ->where(fn ($query) => $query->where('tenant_id', app(TenantContext::class)->id()))
            ->ignore($this->unitId(), 'row_id');
    }
}

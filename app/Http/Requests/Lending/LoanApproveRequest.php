<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Domain\Membership\Models\Member;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanApproveRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'approved_at' => ['required', 'date', 'before_or_equal:today'],
            'planned_disbursed_at' => ['required', 'date', 'after_or_equal:approved_at'],
            'allocated_principal' => ['required', 'numeric', 'min:0'],
            'allocation_notes' => ['nullable', 'string', 'max:500'],
            'beneficiaries' => ['required', 'array', 'min:1'],
            'beneficiaries.*.member_row_id' => ['required', 'integer', Rule::exists(Member::class, 'row_id')->where(fn ($query) => $query->where('tenant_id', $tenantId))],
            'beneficiaries.*.allocated_amount' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'approved_at' => 'tanggal penetapan',
            'planned_disbursed_at' => 'rencana tanggal pencairan',
            'allocated_principal' => 'plafon alokasi kelompok',
            'beneficiaries' => 'alokasi per anggota',
            'beneficiaries.*.member_row_id' => 'anggota',
            'beneficiaries.*.allocated_amount' => 'nominal alokasi',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanUpdateRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'proposed_at' => ['required', 'date', 'before_or_equal:today'],
            'principal_amount' => ['required', 'numeric', 'min:1'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:5000'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'installment_method' => ['required', Rule::in(['flat', 'annuity', 'effective'])],
            'principal_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'at_maturity'])],
            'interest_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly'])],
            'beneficiary_amounts' => ['required', 'array', 'min:1'],
            'beneficiary_amounts.*' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'proposed_at' => 'tanggal pengajuan',
            'principal_amount' => 'plafon pinjaman',
            'service_rate_total' => 'prosentase jasa',
            'term_months' => 'jangka waktu',
            'installment_method' => 'jenis jasa',
            'principal_frequency' => 'sistem angsuran pokok',
            'interest_frequency' => 'sistem angsuran jasa',
            'beneficiary_amounts' => 'pengajuan pemanfaat',
            'beneficiary_amounts.*' => 'pengajuan pemanfaat',
        ];
    }
}

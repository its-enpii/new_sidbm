<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoanRescheduleRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
        return [
            'rescheduled_at' => ['required', 'date', 'before_or_equal:today'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'service_rate_total' => ['required', 'numeric', 'min:0', 'max:100'],
            'installment_method' => ['required', Rule::in(['flat', 'annuity', 'effective'])],
            'principal_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'at_maturity'])],
            'interest_frequency' => ['required', Rule::in(['weekly', 'biweekly', 'monthly', 'bimonthly', 'quarterly', 'at_maturity'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'rescheduled_at' => 'tanggal reschedule',
            'term_months' => 'jangka waktu',
            'service_rate_total' => 'prosentase jasa',
            'installment_method' => 'metode hitung jasa',
            'principal_frequency' => 'frekuensi pokok',
            'interest_frequency' => 'frekuensi jasa',
        ];
    }
}

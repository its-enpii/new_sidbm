<?php

declare(strict_types=1);

namespace App\Http\Requests\Lending;

use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;

final class LoanVerifyRequest extends FormRequest
{
    use AuthorizesPermission;


    public function rules(): array
    {
        return [
            'verified_at' => ['required', 'date', 'before_or_equal:today'],
            'verification_amount' => ['nullable', 'numeric', 'min:0'],
            'verification_notes' => ['nullable', 'string', 'min:3', 'max:5000'],
            'verified_amounts' => ['nullable', 'array'],
            'verified_amounts.*' => ['numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'verified_at' => 'tanggal verifikasi',
            'verification_amount' => 'nominal verifikasi',
            'verification_notes' => 'catatan verifikasi',
            'verified_amounts' => 'nominal verifikasi per pemanfaat',
            'verified_amounts.*' => 'nominal verifikasi per pemanfaat',
        ];
    }
}

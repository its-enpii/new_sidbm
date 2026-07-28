<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public const EDUCATIONS = ['sd', 'smp', 'sma_smk', 'd3', 's1', 's2', 's3'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $userId = $this->user()?->getKey();

        return [
            'nik' => [
                'nullable',
                'digits:16',
                Rule::unique('users', 'nik')->connection('platform')->ignore($userId, 'row_id'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'initials' => ['nullable', 'string', 'max:10'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'education' => ['nullable', Rule::in(self::EDUCATIONS)],
            'appointed_at' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'nik' => 'NIK',
            'name' => 'nama lengkap',
            'initials' => 'inisial',
            'birth_place' => 'tempat lahir',
            'birth_date' => 'tanggal lahir',
            'address' => 'alamat',
            'phone' => 'telepon',
            'education' => 'pendidikan',
            'appointed_at' => 'tanggal menjabat',
        ];
    }
}

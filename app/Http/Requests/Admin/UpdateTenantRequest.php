<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'district_code' => ['nullable', 'string', 'regex:/^\d{6}$/'],
            'status' => ['required', Rule::in(['active', 'suspended', 'provisioning', 'provisioning_failed'])],
            'timezone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama tenant',
            'status' => 'status',
            'timezone' => 'zona waktu',
        ];
    }
}

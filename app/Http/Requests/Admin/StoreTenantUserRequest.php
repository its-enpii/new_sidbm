<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class StoreTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'username' => 'username',
            'email' => 'email',
            'password' => 'password',
            'status' => 'status',
        ];
    }
}

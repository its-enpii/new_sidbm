<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateTenantUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('role') === '') {
            $this->merge(['role' => null]);
        }
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:150'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('users', 'username')->ignore($target->row_id, 'row_id')],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($target->row_id, 'row_id')],
            'password' => ['nullable', 'string', 'confirmed', Password::defaults()],
            'status' => ['required', Rule::in(['active', 'suspended', 'inactive'])],
            'role' => ['nullable', 'string', Rule::in(array_keys(config('permissions.roles', [])))],
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
            'role' => 'role',
        ];
    }
}

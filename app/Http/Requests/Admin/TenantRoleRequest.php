<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Payload contract for superadmin-managed tenant roles.
 *
 * Code uniqueness is checked by the controller because the `roles` table lives
 * on the tenant shard, which is only reachable inside a TenantWorkbench run.
 */
final class TenantRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash'],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama role',
            'code' => 'kode role',
            'description' => 'deskripsi',
            'permissions' => 'hak akses',
        ];
    }
}

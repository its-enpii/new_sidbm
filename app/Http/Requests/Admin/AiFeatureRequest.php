<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AiFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        if ($this->routeIs('admin.features.ai.bulk') || $this->has('tenant_ids')) {
            return [
                'tenant_ids' => ['required', 'array', 'min:1'],
                'tenant_ids.*' => ['required', 'integer', Rule::exists('tenants', 'row_id')],
                'value' => ['required', 'in:on,off,inherit'],
            ];
        }

        if ($this->routeIs('admin.features.ai.global')) {
            return [
                'enabled' => ['required', 'boolean'],
            ];
        }

        return [
            'value' => ['required', 'in:on,off,inherit'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tenant_ids' => 'daftar tenant',
            'tenant_ids.*' => 'tenant terpilih',
            'value' => 'status fitur AI',
            'enabled' => 'kunci global AI',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_superadmin === true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', Rule::exists('tenants', 'row_id')],
            'subscription_id' => ['nullable', 'integer', Rule::exists('subscriptions', 'row_id')],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'due_at' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(['draft', 'issued'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'tenant_id' => 'tenant',
            'subscription_id' => 'langganan',
            'amount' => 'nominal',
            'due_at' => 'jatuh tempo',
            'description' => 'deskripsi',
        ];
    }
}

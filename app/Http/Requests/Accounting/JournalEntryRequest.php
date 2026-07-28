<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Domain\Accounting\Models\Account;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JournalEntryRequest extends FormRequest
{
    use AuthorizesPermission;

    public const TRANSACTION_TYPES = [
        'aset_masuk',
        'aset_keluar',
        'pemindahan_saldo',
        'pembelian_inventaris',
        'penyusutan_inventaris',
        'cadangan_kerugian_piutang',
    ];


    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $level4Exists = Rule::exists(Account::class, 'row_id')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('is_active', true)->where('is_postable', true));

        $inventory = $this->input('transaction_type') === 'pembelian_inventaris';

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', Rule::in(self::TRANSACTION_TYPES)],
            'description' => [$inventory ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sumber_dana_row_id' => ['required', 'integer', 'different:disimpan_ke_row_id', $level4Exists],
            'disimpan_ke_row_id' => ['required', 'integer', 'different:sumber_dana_row_id', $level4Exists],
            // Pembelian inventaris
            'asset_name' => [$inventory ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [$inventory ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [$inventory ? 'required' : 'nullable', 'numeric', 'min:1'],
            'asset_useful_life_months' => [$inventory ? 'required' : 'nullable', 'integer', 'min:1', 'max:1200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('transaction_type') !== 'pembelian_inventaris') {
                return;
            }

            $qty = (int) $this->input('asset_quantity', 0);
            $unit = (float) $this->input('asset_unit_cost', 0);
            $expected = round($qty * $unit, 2);
            $amount = round((float) $this->input('amount', 0), 2);

            if ($qty > 0 && $unit > 0 && $amount !== $expected) {
                $validator->errors()->add(
                    'amount',
                    'Harga perolehan harus sama dengan jml unit × harga satuan ('.number_format($expected, 0, ',', '.').').',
                );
            }
        });
    }

    public function attributes(): array
    {
        return [
            'transaction_date' => 'tanggal transaksi',
            'transaction_type' => 'jenis transaksi',
            'description' => 'keterangan',
            'reference' => 'relasi',
            'amount' => 'harga perolehan',
            'sumber_dana_row_id' => 'sumber dana',
            'disimpan_ke_row_id' => 'disimpan ke',
            'asset_name' => 'nama barang',
            'asset_quantity' => 'jumlah unit',
            'asset_unit_cost' => 'harga satuan',
            'asset_useful_life_months' => 'umur ekonomis',
        ];
    }

    public function messages(): array
    {
        return [
            'sumber_dana_row_id.different' => 'Akun sumber dana dan disimpan ke tidak boleh sama.',
            'disimpan_ke_row_id.different' => 'Akun sumber dana dan disimpan ke tidak boleh sama.',
        ];
    }
}

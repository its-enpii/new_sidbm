<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\JournalEntryOptionResolver;
use App\Http\Requests\Concerns\AuthorizesPermission;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class JournalEntryRequest extends FormRequest
{
    use AuthorizesPermission;

    public function rules(): array
    {
         = app(TenantContext::class)->id();
         = Rule::exists(Account::class, 'row_id')
            ->where(fn () => ->where('tenant_id', )->where('is_active', true)->where('is_postable', true));

         = (string) ->input('transaction_type', '');
         = JournalEntryOptionResolver::isAssetPurchase();
         =  === 'pembelian_aset_tanah';
         = array_merge(array_keys(JournalEntryOptionResolver::TYPES), ['pembelian_inventaris', 'angsuran']);

        return [
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'transaction_type' => ['required', Rule::in()],
            'description' => [ ? 'nullable' : 'required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1'],
            'sumber_dana_row_id' => ['required', 'integer', 'different:disimpan_ke_row_id', ],
            'disimpan_ke_row_id' => ['required', 'integer', 'different:sumber_dana_row_id', ],
            'asset_name' => [ ? 'required' : 'nullable', 'string', 'max:180'],
            'asset_quantity' => [ ? 'required' : 'nullable', 'integer', 'min:1', 'max:999999'],
            'asset_unit_cost' => [ ? 'required' : 'nullable', 'numeric', 'min:1'],
            // Tanah: 0 / null = tidak disusutkan; lainnya min 1.
            'asset_useful_life_months' => [
                 ? 'required' : 'nullable',
                'integer',
                 ? 'min:0' : 'min:1',
                'max:1200',
            ],
        ];
    }

    public function withValidator(): void
    {
        ->after(function (): void {
            if (! JournalEntryOptionResolver::isAssetPurchase((string) ->input('transaction_type', ''))) {
                return;
            }

             = (int) ->input('asset_quantity', 0);
             = (float) ->input('asset_unit_cost', 0);
             = round( * , 2);
             = round((float) ->input('amount', 0), 2);

            if ( > 0 &&  > 0 &&  !== ) {
                ->errors()->add(
                    'amount',
                    'Harga perolehan harus sama dengan jml unit × harga satuan ('.number_format(, 0, ',', '.').').',
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

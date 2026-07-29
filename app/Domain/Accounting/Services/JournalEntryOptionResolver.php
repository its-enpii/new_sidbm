<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;

final class JournalEntryOptionResolver
{
    /** Jenis beli aset → akun debit COA 1.2.01.xx (auto-select). */
    public const ASSET_PURCHASE_TYPES = [
        'pembelian_aset_tanah' => '1.2.01.01',
        'pembelian_aset_gedung' => '1.2.01.02',
        'pembelian_aset_kendaraan' => '1.2.01.03',
        'pembelian_aset_peralatan' => '1.2.01.04',
    ];

    /** Default umur ekonomis (bulan); tanah = 0 (tidak disusutkan). */
    public const ASSET_PURCHASE_DEFAULT_LIFE = [
        'pembelian_aset_tanah' => 0,
        'pembelian_aset_gedung' => 240,
        'pembelian_aset_kendaraan' => 60,
        'pembelian_aset_peralatan' => 48,
    ];

    public const TYPES = [
        'aset_masuk' => 'Aset Masuk',
        'aset_keluar' => 'Aset Keluar',
        'pemindahan_saldo' => 'Pemindahan Saldo/Aset',
        'pembelian_aset_tanah' => 'Pembelian Aset Tanah',
        'pembelian_aset_gedung' => 'Pembelian Aset Gedung & Bangunan',
        'pembelian_aset_kendaraan' => 'Pembelian Aset Kendaraan & Mesin',
        'pembelian_aset_peralatan' => 'Pembelian Aset Inventaris/Peralatan',
        'penyusutan_inventaris' => 'Penyusutan Inventaris',
        'cadangan_kerugian_piutang' => 'Cadangan Kerugian Piutang',
        // 'angsuran' sengaja tidak di TYPES UI — input lewat /journal-entries/installment
    ];

    /** @var array<string, string> optgroup labels for UI */
    public const TYPE_GROUPS = [
        'aset_masuk' => 'Umum',
        'aset_keluar' => 'Umum',
        'pemindahan_saldo' => 'Umum',
        'pembelian_aset_tanah' => 'Pembelian Aset',
        'pembelian_aset_gedung' => 'Pembelian Aset',
        'pembelian_aset_kendaraan' => 'Pembelian Aset',
        'pembelian_aset_peralatan' => 'Pembelian Aset',
        'penyusutan_inventaris' => 'Penyesuaian',
        'cadangan_kerugian_piutang' => 'Penyesuaian',
    ];

    public const LABELS = [
        'aset_masuk' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'aset_keluar' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Keperluan'],
        'pemindahan_saldo' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'pembelian_aset_tanah' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Tanah'],
        'pembelian_aset_gedung' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Gedung'],
        'pembelian_aset_kendaraan' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Kendaraan'],
        'pembelian_aset_peralatan' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Inventaris'],
        'penyusutan_inventaris' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'cadangan_kerugian_piutang' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'angsuran' => ['sumber_dana' => 'Tujuan', 'disimpan_ke' => 'Akun Kredit'],
    ];

    /** @var array<string, array{starts_with?: array<int, string>, not_starts_with?: array<int, string>, exclude_codes?: array<int, string>, exact?: string, all?: bool}> */
    private const RULES = [
        'aset_masuk:sumber_dana' => [
            'exclude_codes' => ['2.1.04.01', '2.1.04.02', '2.1.04.03', '2.1.02.01', '2.1.03.01'],
            'not_starts_with' => ['4.1.01'],
        ],
        'aset_masuk:disimpan_ke' => [
            'starts_with' => ['1.'],
        ],
        'aset_keluar:sumber_dana' => [
            'not_starts_with' => ['2.1.04'],
        ],
        'aset_keluar:disimpan_ke' => [
            'starts_with' => ['2.', '3.', '5.'],
        ],
        'pemindahan_saldo:sumber_dana' => [
            'all' => true,
        ],
        'pemindahan_saldo:disimpan_ke' => [
            'exclude_codes' => ['1.1.03.01', '1.1.03.02', '1.1.03.03'],
        ],
        'pembelian_aset_tanah:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'pembelian_aset_tanah:disimpan_ke' => [
            'exact' => '1.2.01.01',
        ],
        'pembelian_aset_gedung:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'pembelian_aset_gedung:disimpan_ke' => [
            'exact' => '1.2.01.02',
        ],
        'pembelian_aset_kendaraan:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'pembelian_aset_kendaraan:disimpan_ke' => [
            'exact' => '1.2.01.03',
        ],
        'pembelian_aset_peralatan:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'pembelian_aset_peralatan:disimpan_ke' => [
            'exact' => '1.2.01.04',
        ],
        // Legacy type (jurnal lama) — keep filter working if still posted
        'pembelian_inventaris:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'pembelian_inventaris:disimpan_ke' => [
            'starts_with' => ['1.2.01'],
        ],
        'penyusutan_inventaris:sumber_dana' => [
            'starts_with' => ['1.2.02'],
        ],
        'penyusutan_inventaris:disimpan_ke' => [
            'starts_with' => ['5.1.07'],
        ],
        'cadangan_kerugian_piutang:sumber_dana' => [
            'exact' => '1.1.04.01',
        ],
        'cadangan_kerugian_piutang:disimpan_ke' => [
            'exact' => '5.1.07.01',
        ],
        'angsuran:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'angsuran:disimpan_ke' => [
            'all' => true,
        ],
    ];

    public static function isAssetPurchase(?string $type): bool
    {
        return is_string($type) && (
            isset(self::ASSET_PURCHASE_TYPES[$type])
            || $type === 'pembelian_inventaris'
        );
    }

    /**
     * @return array<int, array{value: string, label: string, group?: string}>
     */
    public function getTransactionTypes(): array
    {
        $types = [];
        foreach (self::TYPES as $value => $label) {
            $row = ['value' => $value, 'label' => $label];
            if (isset(self::TYPE_GROUPS[$value])) {
                $row['group'] = self::TYPE_GROUPS[$value];
            }
            $types[] = $row;
        }

        return $types;
    }

    /**
     * @return array<string, array{sumber_dana: string, disimpan_ke: string}>
     */
    public function getLabels(): array
    {
        return self::LABELS;
    }

    /**
     * @return array{sumber_dana: array<int, array{value: int, label: string}>, disimpan_ke: array<int, array{value: int, label: string}>}
     */
    public function getOptionsFor(string $type): array
    {
        if (! isset(self::TYPES[$type])) {
            return ['sumber_dana' => [], 'disimpan_ke' => []];
        }

        return [
            'sumber_dana' => $this->filter($this->activePostableAccounts(), $type, 'sumber_dana'),
            'disimpan_ke' => $this->filter($this->activePostableAccounts(), $type, 'disimpan_ke'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getAllAccountOptions(): array
    {
        return $this->activePostableAccounts()
            ->map(fn (Account $a): array => [
                'value' => (int) $a->row_id,
                'label' => "{$a->code} · {$a->name} ({$a->account_type})",
            ])
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Account>
     */
    private function activePostableAccounts(): \Illuminate\Support\Collection
    {
        return Account::on('tenant')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('level', 4)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name', 'account_type']);
    }

    /**
     * @return array<string, array{sumber_dana: array<int, array{value: int, label: string}>, disimpan_ke: array<int, array{value: int, label: string}>}>
     */
    public function getOptionsForAllTypes(): array
    {
        $options = [];
        foreach (array_keys(self::TYPES) as $type) {
            $options[$type] = $this->getOptionsFor($type);
        }

        return $options;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Account>  $accounts
     * @return array<int, array{value: int, label: string}>
     */
    private function filter($accounts, string $type, string $side): array
    {
        $rule = self::RULES["{$type}:{$side}"] ?? null;
        if ($rule === null) {
            return [];
        }

        $filtered = $accounts->filter(function (Account $a) use ($rule): bool {
            return $this->matchesRule((string) $a->code, $rule);
        });

        return $filtered->map(fn (Account $a): array => [
            'value' => (int) $a->row_id,
            'label' => "{$a->code} · {$a->name} ({$a->account_type})",
        ])->values()->all();
    }

    /**
     * @param  array{starts_with?: array<int, string>, not_starts_with?: array<int, string>, exclude_codes?: array<int, string>, exact?: string, all?: bool}  $rule
     */
    private function matchesRule(string $code, array $rule): bool
    {
        if (($rule['all'] ?? false) === true) {
            return true;
        }

        if (isset($rule['exact']) && $code !== $rule['exact']) {
            return false;
        }

        if (isset($rule['starts_with']) && $rule['starts_with'] !== []) {
            $matched = false;
            foreach ($rule['starts_with'] as $prefix) {
                if (str_starts_with($code, $prefix)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                return false;
            }
        }

        foreach ($rule['not_starts_with'] ?? [] as $prefix) {
            if (str_starts_with($code, $prefix)) {
                return false;
            }
        }

        foreach ($rule['exclude_codes'] ?? [] as $excluded) {
            if (str_starts_with($code, $excluded)) {
                return false;
            }
        }

        return true;
    }
}

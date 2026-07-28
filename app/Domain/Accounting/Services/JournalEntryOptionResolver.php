<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;

final class JournalEntryOptionResolver
{
    public const TYPES = [
        'aset_masuk' => 'Aset Masuk',
        'aset_keluar' => 'Aset Keluar',
        'pemindahan_saldo' => 'Pemindahan Saldo/Aset',
        'pembelian_inventaris' => 'Pembelian Inventaris',
        'penyusutan_inventaris' => 'Penyusutan Inventaris',
        'cadangan_kerugian_piutang' => 'Cadangan Kerugian Piutang',
        'angsuran' => 'Jurnal Angsuran',
    ];

    public const LABELS = [
        'aset_masuk' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'aset_keluar' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Keperluan'],
        'pemindahan_saldo' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'pembelian_inventaris' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function getTransactionTypes(): array
    {
        $types = [];
        foreach (self::TYPES as $value => $label) {
            $types[] = ['value' => $value, 'label' => $label];
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

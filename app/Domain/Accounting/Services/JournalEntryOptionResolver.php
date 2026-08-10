<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;

final class JournalEntryOptionResolver
{
    /** Jenis beli aset → akun debit COA 1.2.01.xx (auto-select bila spesifik). */
    public const ASSET_PURCHASE_TYPES = [
        'pembelian_aset_tanah' => '1.2.01.01',
        'pembelian_aset_gedung' => '1.2.01.02',
        'pembelian_aset_kendaraan' => '1.2.01.03',
        'pembelian_aset_peralatan' => '1.2.01.04',
        'pengakuan_aset_dari_dp' => null,
    ];

    /** Default umur ekonomis (bulan); tanah = 0 (tidak disusutkan). */
    public const ASSET_PURCHASE_DEFAULT_LIFE = [
        'pembelian_aset_tanah' => 0,
        'pembelian_aset_gedung' => 240,
        'pembelian_aset_kendaraan' => 60,
        'pembelian_aset_peralatan' => 48,
        'pengakuan_aset_dari_dp' => 48,
    ];

    public const TYPES = [
        // Umum
        'aset_masuk' => 'Aset Masuk (Penerimaan)',
        'aset_keluar' => 'Aset Keluar (Beban / Pengeluaran)',
        'pemindahan_saldo' => 'Pemindahan Saldo / Mutasi Kas-Bank',
        'investasi_unit_usaha' => 'Investasi Unit Usaha',

        // Pembelian Aset & Uang Muka
        'pembelian_aset_tanah' => 'Pembelian Aset Tanah',
        'pembelian_aset_gedung' => 'Pembelian Aset Gedung & Bangunan',
        'pembelian_aset_kendaraan' => 'Pembelian Aset Kendaraan & Mesin',
        'pembelian_aset_peralatan' => 'Pembelian Aset Inventaris/Peralatan',
        'pembelian_aset_tak_berwujud' => 'Pembelian Aset Tak Berwujud (Sewa/Lisensi/Asuransi)',
        'uang_muka_konstruksi' => 'Uang Muka / Konstruksi Dalam Pengerjaan',
        'pengakuan_aset_dari_dp' => 'Pengakuan Aset Tetap dari Uang Muka',

        // Kewajiban & Modal
        'penerimaan_utang_pihak_ketiga' => 'Penerimaan Utang Bank / Pihak ke-3',
        'pembayaran_utang_pihak_ketiga' => 'Pembayaran Utang Bank & Bunga',
        'penyertaan_modal' => 'Penyertaan Modal Desa / Masyarakat',
        'pembayaran_utang_laba' => 'Pembayaran Utang Laba Bagian Desa/Masyarakat',
        'pembayaran_pajak' => 'Pembayaran Utang Pajak PPh',
        'pembayaran_bonus_prestasi' => 'Pembayaran Utang Bonus Prestasi Kerja',

        // Penyesuaian, Penyusutan & Amortisasi
        'penyusutan_gedung' => 'Beban Penyusutan Gedung & Bangunan',
        'penyusutan_kendaraan' => 'Beban Penyusutan Kendaraan & Mesin',
        'penyusutan_inventaris' => 'Beban Penyusutan Inventaris & Peralatan',
        'amortisasi_aset_tak_berwujud' => 'Beban Amortisasi Aset Tak Berwujud',
        'cadangan_kerugian_piutang' => 'Penyisihan Cadangan Kerugian Piutang (CKP)',
        'pengakuan_utang_laba' => 'Pengakuan Utang Laba Bagian Desa/Masyarakat',
        'taksiran_pph_pajak' => 'Pengakuan Taksiran PPh Pajak',
        'utang_bonus_prestasi' => 'Pengakuan Utang Bonus Prestasi Kerja',
        'penghapusan_aset' => 'Penghapusan Aset Tetap (ATI)',
    ];

    /** @var array<string, string> optgroup labels for UI */
    public const TYPE_GROUPS = [
        'aset_masuk' => 'Umum',
        'aset_keluar' => 'Umum',
        'pemindahan_saldo' => 'Umum',
        'investasi_unit_usaha' => 'Umum',

        'pembelian_aset_tanah' => 'Pembelian Aset & DP',
        'pembelian_aset_gedung' => 'Pembelian Aset & DP',
        'pembelian_aset_kendaraan' => 'Pembelian Aset & DP',
        'pembelian_aset_peralatan' => 'Pembelian Aset & DP',
        'pembelian_aset_tak_berwujud' => 'Pembelian Aset & DP',
        'uang_muka_konstruksi' => 'Pembelian Aset & DP',
        'pengakuan_aset_dari_dp' => 'Pembelian Aset & DP',

        'penerimaan_utang_pihak_ketiga' => 'Kewajiban & Modal',
        'pembayaran_utang_pihak_ketiga' => 'Kewajiban & Modal',
        'penyertaan_modal' => 'Kewajiban & Modal',
        'pembayaran_utang_laba' => 'Kewajiban & Modal',
        'pembayaran_pajak' => 'Kewajiban & Modal',
        'pembayaran_bonus_prestasi' => 'Kewajiban & Modal',

        'penyusutan_gedung' => 'Penyesuaian & Penyusutan',
        'penyusutan_kendaraan' => 'Penyesuaian & Penyusutan',
        'penyusutan_inventaris' => 'Penyesuaian & Penyusutan',
        'amortisasi_aset_tak_berwujud' => 'Penyesuaian & Penyusutan',
        'cadangan_kerugian_piutang' => 'Penyesuaian & Penyusutan',
        'pengakuan_utang_laba' => 'Penyesuaian & Penyusutan',
        'taksiran_pph_pajak' => 'Penyesuaian & Penyusutan',
        'utang_bonus_prestasi' => 'Penyesuaian & Penyusutan',
        'penghapusan_aset' => 'Penyesuaian & Penyusutan',
    ];

    public const LABELS = [
        'aset_masuk' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'aset_keluar' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Keperluan'],
        'pemindahan_saldo' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Disimpan Ke'],
        'investasi_unit_usaha' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Investasi'],

        'pembelian_aset_tanah' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Tanah'],
        'pembelian_aset_gedung' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Gedung'],
        'pembelian_aset_kendaraan' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Kendaraan'],
        'pembelian_aset_peralatan' => ['sumber_dana' => 'Sumber Dana', 'disimpan_ke' => 'Akun Inventaris'],
        'pembelian_aset_tak_berwujud' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Aset Tak Berwujud'],
        'uang_muka_konstruksi' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Uang Muka / Konstruksi'],
        'pengakuan_aset_dari_dp' => ['sumber_dana' => 'Akun Uang Muka / Konstruksi', 'disimpan_ke' => 'Akun Aset Tetap'],

        'penerimaan_utang_pihak_ketiga' => ['sumber_dana' => 'Akun Utang Bank / Lembaga', 'disimpan_ke' => 'Disimpan Ke (Kas/Bank)'],
        'pembayaran_utang_pihak_ketiga' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Keperluan (Utang/Bunga)'],
        'penyertaan_modal' => ['sumber_dana' => 'Akun Modal Desa/Masyarakat', 'disimpan_ke' => 'Disimpan Ke (Kas/Bank)'],
        'pembayaran_utang_laba' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Utang Laba'],
        'pembayaran_pajak' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Utang Pajak'],
        'pembayaran_bonus_prestasi' => ['sumber_dana' => 'Sumber Dana (Kas/Bank)', 'disimpan_ke' => 'Akun Utang Bonus'],

        'penyusutan_gedung' => ['sumber_dana' => 'Akumulasi Penyusutan Gedung', 'disimpan_ke' => 'Beban Penyusutan Gedung'],
        'penyusutan_kendaraan' => ['sumber_dana' => 'Akumulasi Penyusutan Kendaraan', 'disimpan_ke' => 'Beban Penyusutan Kendaraan'],
        'penyusutan_inventaris' => ['sumber_dana' => 'Akumulasi Penyusutan Inventaris', 'disimpan_ke' => 'Beban Penyusutan Inventaris'],
        'amortisasi_aset_tak_berwujud' => ['sumber_dana' => 'Akumulasi Amortisasi', 'disimpan_ke' => 'Beban Amortisasi'],
        'cadangan_kerugian_piutang' => ['sumber_dana' => 'Cadangan Kerugian Piutang', 'disimpan_ke' => 'Beban Penyisihan Kerugian'],
        'pengakuan_utang_laba' => ['sumber_dana' => 'Akun Utang Laba Bagian Desa/Masyarakat', 'disimpan_ke' => 'Laba Berjalan / Beban Laba'],
        'taksiran_pph_pajak' => ['sumber_dana' => 'Akun Utang Pajak PPh', 'disimpan_ke' => 'Beban Pajak Penghasilan'],
        'utang_bonus_prestasi' => ['sumber_dana' => 'Akun Utang Bonus Prestasi', 'disimpan_ke' => 'Beban Bonus Prestasi'],
        'penghapusan_aset' => ['sumber_dana' => 'Akumulasi Penyusutan Aset', 'disimpan_ke' => 'Beban Penghapusan Aset Tetap'],
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
        'investasi_unit_usaha:sumber_dana' => [
            'starts_with' => ['1.1.01'],
        ],
        'investasi_unit_usaha:disimpan_ke' => [
            'starts_with' => ['1.1.06'],
        ],

        // Pembelian Aset
        'pembelian_aset_tanah:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_aset_tanah:disimpan_ke' => ['exact' => '1.2.01.01'],
        'pembelian_aset_gedung:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_aset_gedung:disimpan_ke' => ['exact' => '1.2.01.02'],
        'pembelian_aset_kendaraan:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_aset_kendaraan:disimpan_ke' => ['exact' => '1.2.01.03'],
        'pembelian_aset_peralatan:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_aset_peralatan:disimpan_ke' => ['exact' => '1.2.01.04'],
        'pembelian_inventaris:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_inventaris:disimpan_ke' => ['exact' => '1.2.01.04'],
        'pembelian_aset_tak_berwujud:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembelian_aset_tak_berwujud:disimpan_ke' => ['starts_with' => ['1.2.03']],
        'uang_muka_konstruksi:sumber_dana' => ['starts_with' => ['1.1.01']],
        'uang_muka_konstruksi:disimpan_ke' => ['exact' => '1.2.05.01'],
        'pengakuan_aset_dari_dp:sumber_dana' => ['exact' => '1.2.05.01'],
        'pengakuan_aset_dari_dp:disimpan_ke' => ['starts_with' => ['1.2.01']],

        // Kewajiban & Modal
        'penerimaan_utang_pihak_ketiga:sumber_dana' => ['starts_with' => ['2.1.01', '2.2.01', '2.1.05', '2.2.02']],
        'penerimaan_utang_pihak_ketiga:disimpan_ke' => ['starts_with' => ['1.1.01']],
        'pembayaran_utang_pihak_ketiga:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembayaran_utang_pihak_ketiga:disimpan_ke' => ['starts_with' => ['2.1.01', '2.2.01', '2.1.05', '2.2.02', '5.1']],
        'penyertaan_modal:sumber_dana' => ['starts_with' => ['3.1.01', '3.1.02']],
        'penyertaan_modal:disimpan_ke' => ['starts_with' => ['1.1.01']],
        'pembayaran_utang_laba:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembayaran_utang_laba:disimpan_ke' => ['starts_with' => ['2.1.04']],
        'pembayaran_pajak:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembayaran_pajak:disimpan_ke' => ['exact' => '2.1.03.01'],
        'pembayaran_bonus_prestasi:sumber_dana' => ['starts_with' => ['1.1.01']],
        'pembayaran_bonus_prestasi:disimpan_ke' => ['exact' => '2.1.02.04'],

        // Penyesuaian & Penyusutan
        'penyusutan_gedung:sumber_dana' => ['exact' => '1.2.02.01'],
        'penyusutan_gedung:disimpan_ke' => ['exact' => '5.3.01.01'],
        'penyusutan_kendaraan:sumber_dana' => ['exact' => '1.2.02.02'],
        'penyusutan_kendaraan:disimpan_ke' => ['exact' => '5.3.01.02'],
        'penyusutan_inventaris:sumber_dana' => ['exact' => '1.2.02.03'],
        'penyusutan_inventaris:disimpan_ke' => ['exact' => '5.3.01.03'],
        'amortisasi_aset_tak_berwujud:sumber_dana' => ['starts_with' => ['1.2.04']],
        'amortisasi_aset_tak_berwujud:disimpan_ke' => ['starts_with' => ['5.3.01']],
        'cadangan_kerugian_piutang:sumber_dana' => ['starts_with' => ['1.1.04']],
        'cadangan_kerugian_piutang:disimpan_ke' => ['starts_with' => ['5.1.07']],
        'pengakuan_utang_laba:sumber_dana' => ['starts_with' => ['2.1.04']],
        'pengakuan_utang_laba:disimpan_ke' => ['starts_with' => ['3.2', '5.']],
        'taksiran_pph_pajak:sumber_dana' => ['exact' => '2.1.03.01'],
        'taksiran_pph_pajak:disimpan_ke' => ['starts_with' => ['5.4.01']],
        'utang_bonus_prestasi:sumber_dana' => ['exact' => '2.1.02.04'],
        'utang_bonus_prestasi:disimpan_ke' => ['exact' => '5.1.02.05'],
        'penghapusan_aset:sumber_dana' => ['starts_with' => ['1.2.02']],
        'penghapusan_aset:disimpan_ke' => ['exact' => '5.3.02.01'],

        'angsuran:sumber_dana' => ['starts_with' => ['1.1.01']],
        'angsuran:disimpan_ke' => ['all' => true],
    ];

    public static function isAssetPurchase(?string ): bool
    {
        return is_string() && (
            isset(self::ASSET_PURCHASE_TYPES[])
            ||  === 'pembelian_inventaris'
        );
    }

    /**
     * @return array<int, array{value: string, label: string, group?: string}>
     */
    public function getTransactionTypes(): array
    {
         = [];
        foreach (self::TYPES as  => ) {
             = ['value' => , 'label' => ];
            if (isset(self::TYPE_GROUPS[])) {
                ['group'] = self::TYPE_GROUPS[];
            }
            [] = ;
        }

        return ;
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
    public function getOptionsFor(string ): array
    {
        if (! isset(self::TYPES[])) {
            return ['sumber_dana' => [], 'disimpan_ke' => []];
        }

        return [
            'sumber_dana' => ->filter(->activePostableAccounts(), , 'sumber_dana'),
            'disimpan_ke' => ->filter(->activePostableAccounts(), , 'disimpan_ke'),
        ];
    }

    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function getAllAccountOptions(): array
    {
        return ->activePostableAccounts()
            ->map(fn (Account ): array => [
                'value' => (int) ->row_id,
                'label' => "{->code} · {->name} ({->account_type})",
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
         = [];
        foreach (array_keys(self::TYPES) as ) {
            [] = ->getOptionsFor();
        }

        return ;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Account>  
     * @return array<int, array{value: int, label: string}>
     */
    private function filter(, string , string ): array
    {
         = self::RULES["{}:{}"] ?? null;
        if ( === null) {
            return [];
        }

         = ->filter(function (Account ) use (): bool {
            return ->matchesRule((string) ->code, );
        });

        return ->map(fn (Account ): array => [
            'value' => (int) ->row_id,
            'label' => "{->code} · {->name} ({->account_type})",
        ])->values()->all();
    }

    /**
     * @param  array{starts_with?: array<int, string>, not_starts_with?: array<int, string>, exclude_codes?: array<int, string>, exact?: string, all?: bool}  
     */
    private function matchesRule(string , array ): bool
    {
        if ((['all'] ?? false) === true) {
            return true;
        }

        if (isset(['exact']) &&  !== ['exact']) {
            return false;
        }

        if (isset(['starts_with']) && ['starts_with'] !== []) {
             = false;
            foreach (['starts_with'] as ) {
                if (str_starts_with(, )) {
                     = true;
                    break;
                }
            }
            if (! ) {
                return false;
            }
        }

        foreach (['not_starts_with'] ?? [] as ) {
            if (str_starts_with(, )) {
                return false;
            }
        }

        foreach (['exclude_codes'] ?? [] as ) {
            if (str_starts_with(, )) {
                return false;
            }
        }

        return true;
    }
}

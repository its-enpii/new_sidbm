<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Lending\Services\Reports\LoanPortfolioReportService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ExcelBundleService
{
    public function __construct(
        private readonly BalanceSheetService $balanceSheet,
        private readonly IncomeStatementService $incomeStatement,
        private readonly CashFlowService $cashFlow,
        private readonly TrialBalanceService $trialBalance,
        private readonly GeneralLedgerService $generalLedger,
        private readonly LoanPortfolioReportService $portfolio,
        private readonly TenantContext $tenantContext,
        private readonly AccountBalanceQuery $balances,
    ) {}

    /**
     * @return array{filename: string, path: string, size: int}
     */
    public function build(int $year, int $month): array
    {
        set_time_limit(0);

        $tenantSlug = $this->tenantSlug();
        $filename = sprintf('bundle-auditor-%s%04d-%02d.xlsx', $tenantSlug, $year, $month);
        $path = $this->directory().'/'.$filename;

        $options = new Options;
        $writer = new Writer($options);
        $writer->openToFile($path);

        $bold = (new Style)->withFontBold(true);

        // 1. Ringkasan
        $this->writeSummarySheet($writer, $bold, $year, $month);

        // 2. Neraca
        $this->writeBalanceSheet($writer, $bold, $year, $month);

        // 3. Laba Rugi
        $this->writeIncomeStatement($writer, $bold, $year, $month);

        // 4. Arus Kas
        $this->writeCashFlow($writer, $bold, $year, $month);

        // 5. Neraca Saldo
        $this->writeTrialBalance($writer, $bold, $year, $month);

        // 6. Buku Besar
        $this->writeGeneralLedger($writer, $bold, $year, $month);

        // 7. Piutang
        $this->writePortfolio($writer, $bold, $year, $month);

        $writer->close();

        clearstatcache(true, $path);

        return [
            'filename' => $filename,
            'path' => $path,
            'size' => (int) filesize($path),
        ];
    }

    public function download(int $year, int $month): BinaryFileResponse
    {
        $bundle = $this->build($year, $month);
        $response = response()->download($bundle['path'], $bundle['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        if ($response instanceof BinaryFileResponse) {
            $response->deleteFileAfterSend(true);
        }

        return $response;
    }

    private function writeSummarySheet(Writer $writer, Style $bold, int $year, int $month): void
    {
        $profile = OrganizationProfile::query()->first();
        $legalName = (string) ($profile?->legal_name ?? config('app.name'));
        $district = (string) ($profile?->district_name ?? '');
        $regency = (string) ($profile?->regency_name ?? '');

        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $monthLabel = $monthNames[$month] ?? "Bulan {$month}";

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Ringkasan');

        $writer->addRow(Row::fromValuesWithStyle(['RINGKASAN EKSPOR BUNDLE LAPORAN KEUANGAN (AUDITOR)'], $bold));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['Identitas Lembaga & Periode', ''], $bold));
        $writer->addRow(Row::fromValues(['Nama Lembaga', $legalName]));
        $writer->addRow(Row::fromValues(['Kecamatan', $district]));
        $writer->addRow(Row::fromValues(['Kabupaten / Kota', $regency]));
        $writer->addRow(Row::fromValues(['Periode', "{$monthLabel} {$year}"]));
        $writer->addRow(Row::fromValues(['Tanggal Cetak', now()->format('Y-m-d H:i:s')]));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['No', 'Nama Sheet', 'Deskripsi Lembar Kerja'], $bold));
        $writer->addRow(Row::fromValues([1, 'Ringkasan', 'Identitas entitas, periode, dan daftar lembar kerja']));
        $writer->addRow(Row::fromValues([2, 'Neraca', 'Laporan Posisi Keuangan (Aset, Kewajiban, Ekuitas)']));
        $writer->addRow(Row::fromValues([3, 'Laba Rugi', 'Laporan Laba Rugi Operasional dan Non-Operasional']));
        $writer->addRow(Row::fromValues([4, 'Arus Kas', 'Laporan Arus Kas Metode Langsung']));
        $writer->addRow(Row::fromValues([5, 'Neraca Saldo', 'Neraca Saldo saldo awal, mutasi debet/kredit, saldo akhir']));
        $writer->addRow(Row::fromValues([6, 'Buku Besar', 'Rincian transaksi dan mutasi per akun aktif']));
        $writer->addRow(Row::fromValues([7, 'Piutang', 'Portofolio pinjaman, sisa pokok, tunggakan & kolektibilitas']));
    }

    private function writeBalanceSheet(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Neraca');

        $data = $this->balanceSheet->build($year, $month);

        $writer->addRow(Row::fromValuesWithStyle(['Kode Akun', 'Nama Akun / Pos', 'Level', 'Saldo'], $bold));

        $sections = $data['sections'] ?? [];
        foreach ($sections as $l1) {
            $writer->addRow(Row::fromValuesWithStyle([
                (string) $l1['code'],
                (string) $l1['name'],
                1,
                (float) ($l1['balance'] ?? 0),
            ], $bold));

            foreach ($l1['children'] ?? [] as $l2) {
                $writer->addRow(Row::fromValues([
                    (string) $l2['code'],
                    '  '.(string) $l2['name'],
                    2,
                    '',
                ]));

                foreach ($l2['children'] ?? [] as $l3) {
                    $writer->addRow(Row::fromValues([
                        (string) $l3['code'],
                        '    '.(string) $l3['name'],
                        3,
                        (float) ($l3['balance'] ?? 0),
                    ]));
                }
            }
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['TOTAL ASET', '', '', (float) ($data['total_asset'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['TOTAL KEWAJIBAN & EKUITAS', '', '', (float) ($data['total_credit'] ?? 0)], $bold));
    }

    private function writeIncomeStatement(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Laba Rugi');

        $data = $this->incomeStatement->build($year, $month);

        $writer->addRow(Row::fromValuesWithStyle(['Kode Akun', 'Nama Pos Pendapatan / Beban', 'Bulan Ini', 's.d. Bulan Ini'], $bold));

        $groups = $data['groups'] ?? [];
        foreach ($groups as $group) {
            $writer->addRow(Row::fromValuesWithStyle([
                (string) $group['code'],
                (string) $group['name'],
                (float) ($group['current'] ?? 0),
                (float) ($group['ytd'] ?? 0),
            ], $bold));

            foreach ($group['children'] ?? [] as $child) {
                $writer->addRow(Row::fromValues([
                    (string) $child['code'],
                    '  '.(string) $child['name'],
                    (float) ($child['current'] ?? 0),
                    (float) ($child['ytd'] ?? 0),
                ]));
            }
        }

        $summary = $data['summary'] ?? [];
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['SURPLUS / DEFISIT OPERASIONAL', '', (float) ($summary['operating']['current'] ?? 0), (float) ($summary['operating']['ytd'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['SURPLUS / DEFISIT NON OPERASIONAL', '', (float) ($summary['non_operating']['current'] ?? 0), (float) ($summary['non_operating']['ytd'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['SURPLUS / DEFISIT SEBELUM PAJAK', '', (float) ($summary['before_tax']['current'] ?? 0), (float) ($summary['before_tax']['ytd'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['PAJAK PENGHASILAN', '', (float) ($summary['tax']['current'] ?? 0), (float) ($summary['tax']['ytd'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['SURPLUS / DEFISIT BERSIH SETELAH PAJAK', '', (float) ($summary['after_tax']['current'] ?? 0), (float) ($summary['after_tax']['ytd'] ?? 0)], $bold));
    }

    private function writeCashFlow(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Arus Kas');

        $data = $this->cashFlow->build($year, $month);

        $writer->addRow(Row::fromValuesWithStyle(['Aktivitas', 'Uraian / Keterangan', 'Arus Kas (Rp)'], $bold));

        $writer->addRow(Row::fromValuesWithStyle(['Saldo Awal Kas', 'Saldo kas dan setara kas pada awal periode', (float) ($data['opening_cash'] ?? 0)], $bold));

        $sections = $data['sections'] ?? [];
        foreach ($sections as $secKey => $sec) {
            $writer->addRow(Row::fromValuesWithStyle([(string) ($sec['label'] ?? $secKey), '', (float) ($sec['total'] ?? 0)], $bold));
            foreach ($sec['lines'] ?? [] as $line) {
                $writer->addRow(Row::fromValues([
                    '',
                    '  '.(string) ($line['label'] ?? ''),
                    (float) ($line['amount'] ?? 0),
                ]));
            }
        }

        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle(['Kenaikan / (Penurunan) Kas Bersih', '', (float) ($data['net_change'] ?? 0)], $bold));
        $writer->addRow(Row::fromValuesWithStyle(['Saldo Akhir Kas', 'Saldo kas dan setara kas pada akhir periode', (float) ($data['closing_cash'] ?? 0)], $bold));
    }

    private function writeTrialBalance(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Neraca Saldo');

        $data = $this->trialBalance->build($year, $month, includeZero: true);

        $writer->addRow(Row::fromValuesWithStyle([
            'Kode Akun',
            'Nama Akun',
            'Tipe',
            'Debet (Rp)',
            'Kredit (Rp)',
            'Saldo Bersih (Rp)',
        ], $bold));

        foreach ($data['rows'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                (string) $row['code'],
                (string) $row['name'],
                (string) $row['account_type'],
                (float) ($row['ns_debit'] ?? 0),
                (float) ($row['ns_credit'] ?? 0),
                (float) ($row['signed'] ?? 0),
            ]));
        }

        $totals = $data['totals'] ?? [];
        $writer->addRow(Row::fromValuesWithStyle([
            'TOTAL',
            '',
            '',
            (float) ($totals['ns_debit'] ?? 0),
            (float) ($totals['ns_credit'] ?? 0),
            '',
        ], $bold));
    }

    private function writeGeneralLedger(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Buku Besar');

        $accounts = Account::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['row_id', 'code', 'name']);

        $writer->addRow(Row::fromValuesWithStyle([
            'Kode Akun',
            'Nama Akun',
            'Tanggal',
            'No. Jurnal',
            'Keterangan',
            'Debet (Rp)',
            'Kredit (Rp)',
            'Saldo Berjalan (Rp)',
        ], $bold));

        foreach ($accounts as $account) {
            try {
                $gl = $this->generalLedger->build($year, $month, (int) $account->row_id);
            } catch (\Throwable) {
                continue;
            }

            $hasMovements = ($gl['rows'] ?? []) !== [];
            $opening = (float) ($gl['opening']['prior']['balance'] ?? $gl['opening']['year']['balance'] ?? 0);
            $periodDebit = (float) ($gl['totals']['period']['debit'] ?? 0);
            $periodCredit = (float) ($gl['totals']['period']['credit'] ?? 0);

            if (! $hasMovements && $opening === 0.0 && $periodDebit === 0.0 && $periodCredit === 0.0) {
                continue;
            }

            $writer->addRow(Row::fromValuesWithStyle([
                (string) $account->code,
                (string) $account->name,
                (string) ($gl['period']['from'] ?? ''),
                'SALDO AWAL',
                'Saldo awal periode',
                0.0,
                0.0,
                $opening,
            ], $bold));

            foreach ($gl['rows'] ?? [] as $row) {
                $writer->addRow(Row::fromValues([
                    (string) $account->code,
                    (string) $account->name,
                    (string) ($row['date'] ?? ''),
                    (string) ($row['journal_number'] ?? ''),
                    (string) ($row['description'] ?? ''),
                    (float) ($row['debit'] ?? 0),
                    (float) ($row['credit'] ?? 0),
                    (float) ($row['balance'] ?? 0),
                ]));
            }
        }
    }

    private function writePortfolio(Writer $writer, Style $bold, int $year, int $month): void
    {
        $writer->addNewSheetAndMakeItCurrent();
        $sheet = $writer->getCurrentSheet();
        $sheet->setName('Piutang');

        $asOf = CarbonImmutable::create($year, $month, 1)->endOfMonth()->toDateString();
        $data = $this->portfolio->build($asOf, 'all');

        $writer->addRow(Row::fromValuesWithStyle([
            'No. SPK',
            'Kelompok',
            'Desa',
            'Produk',
            'Tanggal Cair',
            'Plafon Disbursed (Rp)',
            'Sisa Pokok (Rp)',
            'Sisa Jasa (Rp)',
            'Total Tunggakan (Rp)',
            'Hari Nunggak',
            'Aging / Kolektibilitas',
            'Status',
        ], $bold));

        foreach ($data['rows'] ?? [] as $row) {
            $writer->addRow(Row::fromValues([
                (string) ($row['loan_number'] ?? ''),
                (string) ($row['group_name'] ?? ''),
                (string) ($row['village_name'] ?? ''),
                (string) ($row['product_code'] ?? ''),
                (string) ($row['disbursed_at'] ?? ''),
                (float) ($row['principal_disbursed'] ?? 0),
                (float) ($row['principal_remaining'] ?? 0),
                (float) ($row['interest_remaining'] ?? 0),
                (float) ($row['overdue_amount'] ?? 0),
                (int) ($row['days_overdue'] ?? 0),
                (string) ($row['aging_bucket_label'] ?? $row['aging_bucket'] ?? 'Lancar'),
                (string) ($row['status'] ?? ''),
            ]));
        }

        $totals = $data['totals'] ?? [];
        $writer->addRow(Row::fromValuesWithStyle([
            'TOTAL ('.($totals['count'] ?? 0).' pinjaman)',
            '',
            '',
            '',
            '',
            (float) ($totals['principal_disbursed'] ?? 0),
            (float) ($totals['principal_remaining'] ?? 0),
            (float) ($totals['interest_remaining'] ?? 0),
            (float) ($totals['overdue_amount'] ?? 0),
            '',
            '',
            '',
        ], $bold));
    }

    private function tenantSlug(): string
    {
        $profile = OrganizationProfile::query()->first();
        $raw = (string) ($profile?->short_name ?: $profile?->legal_name ?: '');
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($raw)) ?? '';
        $slug = trim($slug, '-');

        return $slug === '' ? '' : $slug.'-';
    }

    private function directory(): string
    {
        $path = storage_path('app/report-bundles');
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new RuntimeException('Gagal membuat direktori bundle laporan.');
        }

        return $path;
    }
}

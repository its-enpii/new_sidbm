<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services\Reports;

use App\Domain\Accounting\Services\AccountBalanceQuery;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Models\Tenant\OrganizationUnit;
use App\Services\TenantSettingService;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * CALK — catatan atas laporan keuangan.
 * Legacy: free-text + template statis. Next: ringkasan otomatis + catatan editable (tenant_settings).
 */
final class CalkService
{
    public const NOTES_KEY = 'calk.notes';

    public function __construct(
        private readonly AccountBalanceQuery $balances,
        private readonly BalanceSheetService $balanceSheet,
        private readonly IncomeStatementService $incomeStatement,
        private readonly CashFlowService $cashFlow,
        private readonly TenantSettingService $settings,
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, ?int $month): array
    {
        $period = $this->balances->resolvePeriod($year, $month);
        $asOf = CarbonImmutable::parse($period['as_of'])->startOfDay();

        $bs = $this->balanceSheet->build($year, $month);
        $is = $this->incomeStatement->build($year, $month);
        $cf = $this->cashFlow->build($year, $month);

        $profile = OrganizationProfile::query()->first();
        $notes = $this->settings->get(self::NOTES_KEY, '');
        if (! is_string($notes)) {
            $notes = is_array($notes) ? (string) ($notes['body'] ?? '') : '';
        }

        // Personalia pengurus: Next belum menyimpan struktur jabatan/nama pengurus
        // secara eksplisit — ambil dari leader/responsible organisasi bila tersedia.
        $personalia = [];
        $unitPersonalia = OrganizationUnit::query()->first(['leader_name', 'responsible_name']);
        if ($unitPersonalia !== null) {
            if (filled($unitPersonalia->leader_name)) {
                $personalia[] = ['sebutan' => 'Ketua', 'nama' => (string) $unitPersonalia->leader_name];
            }
            if (filled($unitPersonalia->responsible_name)) {
                $personalia[] = ['sebutan' => 'Penanggung Jawab', 'nama' => (string) $unitPersonalia->responsible_name];
            }
        }

        $accountingSummary = $this->balanceSheet->buildDetailTree($year, $month);

        $profitDistribution = $this->profitDistribution($year, $month);

        return [
            'period' => $period,
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
                'address' => $profile?->address,
                'registration_number' => $profile?->registration_number,
                'tax_number' => $profile?->tax_number,
            ],
            'notes' => $notes,
            'personalia' => $personalia,
            'accounting_summary' => $accountingSummary,
            'profit_distribution' => $profitDistribution,
            'highlights' => [
                [
                    'key' => 'net_income',
                    'label' => 'Laba (rugi) bersih YTD',
                    'amount' => round((float) ($is['summary']['after_tax']['ytd'] ?? $this->balances->netIncome($asOf)), 2),
                ],
                [
                    'key' => 'total_asset',
                    'label' => 'Total aset',
                    'amount' => round((float) ($bs['totals']['assets'] ?? 0), 2),
                ],
                [
                    'key' => 'total_liability_equity',
                    'label' => 'Total utang + ekuitas',
                    'amount' => round((float) ($bs['totals']['liabilities_equity'] ?? 0), 2),
                ],
                [
                    'key' => 'cash_closing',
                    'label' => 'Saldo kas akhir',
                    'amount' => round((float) ($cf['closing_cash'] ?? 0), 2),
                ],
                [
                    'key' => 'cash_net',
                    'label' => 'Perubahan kas periode',
                    'amount' => round((float) ($cf['net_change'] ?? 0), 2),
                ],
            ],
            'policies' => [
                [
                    'title' => 'Pernyataan Kepatuhan',
                    'items' => [
                        'Laporan keuangan disusun menggunakan Standar Akuntansi Keuangan Perusahaan Jasa Keuangan',
                        'Dasar Penyusunan Kepmendesa 136 Tahun 2022',
                        'Dasar penyusunan laporan keuangan adalah biaya historis dan menggunakan asumsi dasar akrual. Mata uang penyajian yang digunakan untuk menyusun laporan keuangan ini adalah Rupiah.',
                    ],
                ],
                [
                    'title' => 'Piutang Usaha Bersih',
                    'items' => [
                        'Penyajian piutang dalam laporan keuangan dilakukan dengan cara menyajikan nilai bersih yang dapat direalisasikan (net realizable value). Nilai bersih yang dapat direalisasikan adalah selisih antara nilai nominal piutang dengan penyisihan piutang.',
                    ],
                ],
                [
                    'title' => 'Aset Tetap (berwujud dan tidak berwujud)',
                    'items' => [
                        'Aset tetap dinyatakan berdasarkan biaya perolehan dikurangi akumulasi penyusutan. Penyusutan dihitung dengan metode garis lurus sesuai taksiran masa manfaat ekonomis.',
                    ],
                ],
                [
                    'title' => 'Pengakuan Pendapatan dan Beban',
                    'items' => [
                        'Pendapatan diakui pada saat terjadinya transaksi. Beban diakui pada saat terjadinya (basis akrual). Bila suatu pengeluaran telah menikmati manfaat/menerima fasilitas, maka hal tersebut sudah wajib diakui sebagai beban meskipun belum diterbitkan kuitansi pembayaran.',
                    ],
                ],
                [
                    'title' => 'Pajak Penghasilan',
                    'items' => [
                        'Pajak Penghasilan mengikuti ketentuan perpajakan yang berlaku di Indonesia',
                    ],
                ],
            ],
        ];
    }

    /**
     * Pembagian laba per Desa dari saldo akun Modal/ekuitas (prefix 3.1.*) per
     * organization_unit_row_id pada journal_lines. Bila tidak ada mutasi, baris desa
     * tetap ditampilkan dengan nilai 0 (bukan tabel kosong).
     *
     * @return array{villages: list<array<string, mixed>>, retained: list<array<string, mixed>>}
     */
    private function profitDistribution(int $year, ?int $month): array
    {
        $tenantId = $this->context->id();
        $asOf = CarbonImmutable::create($year, $month ?? 12, 1);
        $asOf = ($month === null ? $asOf->endOfYear() : $asOf->endOfMonth())->toDateString();
        $yearStart = sprintf('%04d-01-01', $year);
        $priorEnd = sprintf('%04d-12-31', $year - 1);

        $villages = OrganizationUnit::query()
            ->where('type', 'village')
            ->orderBy('code')
            ->get(['row_id', 'code', 'name']);

        // Saldo ekuitas/modal (3.1.*) per unit organisasi s/d akhir tahun lalu.
        $priorByUnit = DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', function ($j): void {
                $j->on('je.tenant_id', '=', 'jl.tenant_id')
                    ->on('je.row_id', '=', 'jl.journal_entry_row_id');
            })
            ->join('accounts as a', function ($j): void {
                $j->on('a.tenant_id', '=', 'jl.tenant_id')
                    ->on('a.row_id', '=', 'jl.account_row_id');
            })
            ->where('jl.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.transaction_date', '<=', $priorEnd)
            ->where('a.code', 'like', '3.1.%')
            ->whereNotNull('jl.organization_unit_row_id')
            ->groupBy('jl.organization_unit_row_id')
            ->selectRaw('jl.organization_unit_row_id as unit_row_id')
            ->selectRaw('CAST(COALESCE(SUM(jl.credit - jl.debit), 0) AS CHAR) as total')
            ->pluck('total', 'unit_row_id');

        // Mutasi ekuitas/modal tahun berjalan s/d as-of.
        $currentByUnit = DB::connection('tenant')
            ->table('journal_lines as jl')
            ->join('journal_entries as je', function ($j): void {
                $j->on('je.tenant_id', '=', 'jl.tenant_id')
                    ->on('je.row_id', '=', 'jl.journal_entry_row_id');
            })
            ->join('accounts as a', function ($j): void {
                $j->on('a.tenant_id', '=', 'jl.tenant_id')
                    ->on('a.row_id', '=', 'jl.account_row_id');
            })
            ->where('jl.tenant_id', $tenantId)
            ->where('je.status', 'posted')
            ->where('je.transaction_date', '>=', $yearStart)
            ->where('je.transaction_date', '<=', $asOf)
            ->where('a.code', 'like', '3.1.%')
            ->whereNotNull('jl.organization_unit_row_id')
            ->groupBy('jl.organization_unit_row_id')
            ->selectRaw('jl.organization_unit_row_id as unit_row_id')
            ->selectRaw('CAST(COALESCE(SUM(jl.credit - jl.debit), 0) AS CHAR) as total')
            ->pluck('total', 'unit_row_id');

        $villageRows = [];
        foreach ($villages as $village) {
            $prior = round((float) ($priorByUnit[(int) $village->row_id] ?? 0), 2);
            $current = round((float) ($currentByUnit[(int) $village->row_id] ?? 0), 2);
            $villageRows[] = [
                'code' => (string) $village->code,
                'name' => (string) $village->name,
                'prior' => $prior,
                'current' => $current,
                'cumulative' => round($prior + $current, 2),
            ];
        }

        return [
            'villages' => $villageRows,
            'retained' => [],
        ];
    }

    public function saveNotes(string $notes): void
    {
        $this->settings->set(self::NOTES_KEY, $notes, 'string');
    }
}

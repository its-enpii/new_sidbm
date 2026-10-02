<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rencana (due) vs realisasi (paid) angsuran per bulan — subset legacy rencana_realisasi.
 */
final class LoanScheduleVsActualService
{
    private const ACTIVE_LIKE = ['active', 'disbursed', 'completed', 'written_off', 'rescheduled'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));
        $from = CarbonImmutable::create($year, $month, 1)->startOfDay();
        $until = $from->addMonth();
        $fromStr = $from->toDateString();
        $untilStr = $until->toDateString();
        $tenantId = $this->context->id();

        $labels = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $rows = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($j): void {
                $j->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('loan_products as p', function ($j): void {
                $j->on('p.tenant_id', '=', 'l.tenant_id')
                    ->on('p.row_id', '=', 'l.loan_product_row_id');
            })
            ->where('i.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE_LIKE)
            ->where('i.due_date', '>=', $fromStr)
            ->where('i.due_date', '<', $untilStr)
            ->groupBy('l.row_id', 'l.id', 'l.loan_number', 'g.name', 'p.code')
            ->orderBy('g.name')
            ->orderBy('l.id')
            ->selectRaw('l.row_id, l.id, l.loan_number, g.name as group_name, p.code as product_code')
            ->selectRaw('CAST(COALESCE(SUM(i.principal_due), 0) AS CHAR) AS plan_principal')
            ->selectRaw('CAST(COALESCE(SUM(i.interest_due), 0) AS CHAR) AS plan_interest')
            ->selectRaw('CAST(COALESCE(SUM(i.principal_paid), 0) AS CHAR) AS actual_principal')
            ->selectRaw('CAST(COALESCE(SUM(i.interest_paid), 0) AS CHAR) AS actual_interest')
            ->get();

        $out = [];
        $totals = [
            'count' => 0,
            'plan_principal' => 0.0,
            'plan_interest' => 0.0,
            'actual_principal' => 0.0,
            'actual_interest' => 0.0,
            'gap_principal' => 0.0,
            'gap_interest' => 0.0,
        ];

        foreach ($rows as $r) {
            $planP = round((float) $r->plan_principal, 2);
            $planI = round((float) $r->plan_interest, 2);
            $actP = round((float) $r->actual_principal, 2);
            $actI = round((float) $r->actual_interest, 2);
            // paid on installment rows may include payments after due month;
            // still useful as schedule progress for that installment set.
            if ($planP <= 0.009 && $planI <= 0.009 && $actP <= 0.009 && $actI <= 0.009) {
                continue;
            }
            $gapP = round($planP - $actP, 2);
            $gapI = round($planI - $actI, 2);
            $out[] = [
                'row_id' => (int) $r->row_id,
                'id' => (int) $r->id,
                'loan_number' => $r->loan_number ?: ('#'.$r->id),
                'group_name' => $r->group_name ?: '—',
                'product_code' => $r->product_code ?: '—',
                'plan_principal' => $planP,
                'plan_interest' => $planI,
                'actual_principal' => $actP,
                'actual_interest' => $actI,
                'gap_principal' => $gapP,
                'gap_interest' => $gapI,
            ];
            $totals['count']++;
            $totals['plan_principal'] = round($totals['plan_principal'] + $planP, 2);
            $totals['plan_interest'] = round($totals['plan_interest'] + $planI, 2);
            $totals['actual_principal'] = round($totals['actual_principal'] + $actP, 2);
            $totals['actual_interest'] = round($totals['actual_interest'] + $actI, 2);
            $totals['gap_principal'] = round($totals['gap_principal'] + $gapP, 2);
            $totals['gap_interest'] = round($totals['gap_interest'] + $gapI, 2);
        }

        $profile = OrganizationProfile::query()->first(['legal_name', 'short_name']);

        return [
            'year' => $year,
            'month' => $month,
            'period' => [
                'period_label' => ($labels[$month] ?? "Bulan {$month}")." {$year}",
                'as_of' => $until->subDay()->toDateString(),
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?: config('app.name')),
                'short_name' => $profile?->short_name,
            ],
            'rows' => $out,
            'totals' => $totals,
            'monthLabels' => $labels,
            // Data format legacy (rencana_realisasi.blade.php) — dipakai oleh PDF.
            ...$this->pencairan($tenantId, $fromStr, $untilStr),
        ];
    }

    /**
     * Data realisasi pencairan kelompok format legacy.
     *
     * @return array{villages: list<array<string, mixed>>, pencairan_totals: array<string, float|int>}
     */
    private function pencairan(int $tenantId, string $fromStr, string $untilStr): array
    {
        $loans = DB::connection('tenant')
            ->table('loans as l')
            ->leftJoin('loan_borrowers as b', function ($j): void {
                $j->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($j): void {
                $j->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->where('l.tenant_id', $tenantId)
            ->whereIn('l.status', self::ACTIVE_LIKE)
            ->whereNotNull('l.disbursed_at')
            ->where('l.disbursed_at', '>=', $fromStr)
            ->where('l.disbursed_at', '<', $untilStr)
            ->orderBy('v.name')
            ->orderBy('l.id')
            ->selectRaw('l.id, l.loan_number, l.disbursed_at, l.principal_amount, l.term_months, l.installment_method')
            ->selectRaw('g.name as group_name, v.code as village_code, v.name as village_name')
            ->selectRaw('(SELECT COUNT(*) FROM loan_beneficiaries lb WHERE lb.tenant_id = l.tenant_id AND lb.loan_row_id = l.row_id) as beneficiary_count')
            ->get();

        $villagesMap = [];
        $grand = ['kelompok' => 0, 'pemanfaat' => 0, 'pengajuan' => 0.0, 'pencairan' => 0.0];

        foreach ($loans as $loan) {
            $vKey = (string) ($loan->village_code ?? $loan->village_name ?? 'LAIN-LAIN');
            if (! isset($villagesMap[$vKey])) {
                $villagesMap[$vKey] = [
                    'kode_desa' => (string) ($loan->village_code ?? '-'),
                    'nama_desa' => (string) ($loan->village_name ?? 'Lain-lain'),
                    'loans' => [],
                    'subtotal' => ['kelompok' => 0, 'pemanfaat' => 0, 'pengajuan' => 0.0, 'pencairan' => 0.0],
                ];
            }

            $alokasi = (float) $loan->principal_amount;
            $pemanfaat = max(1, (int) ($loan->beneficiary_count ?? 0));

            $villagesMap[$vKey]['loans'][] = [
                'loan_id' => (int) $loan->id,
                'loan_number' => (string) ($loan->loan_number ?? ''),
                'group_name' => (string) ($loan->group_name ?? 'Individu'),
                'ketua' => '',
                'pemanfaat_count' => $pemanfaat,
                'disbursed_at' => (string) $loan->disbursed_at,
                'jangka' => (int) ($loan->term_months ?? 0),
                'sistem_pokok' => (string) ($loan->installment_method ?? ''),
                // Next belum menyimpan nilai pengajuan (proposal) terpisah dari pencairan.
                'proposal' => $alokasi,
                'alokasi' => $alokasi,
            ];

            $villagesMap[$vKey]['subtotal']['kelompok'] += 1;
            $villagesMap[$vKey]['subtotal']['pemanfaat'] += $pemanfaat;
            $villagesMap[$vKey]['subtotal']['pengajuan'] += $alokasi;
            $villagesMap[$vKey]['subtotal']['pencairan'] += $alokasi;

            $grand['kelompok'] += 1;
            $grand['pemanfaat'] += $pemanfaat;
            $grand['pengajuan'] += $alokasi;
            $grand['pencairan'] += $alokasi;
        }

        ksort($villagesMap);

        return [
            'villages' => array_values($villagesMap),
            'pencairan_totals' => [
                'kelompok' => $grand['kelompok'],
                'pemanfaat' => $grand['pemanfaat'],
                'pengajuan' => round($grand['pengajuan'], 2),
                'pencairan' => round($grand['pencairan'], 2),
            ],
        ];
    }
}

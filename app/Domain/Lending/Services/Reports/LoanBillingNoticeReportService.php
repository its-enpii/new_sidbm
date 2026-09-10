<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Documents\Services\SignatureImageService;
use App\Domain\Documents\Services\SignatureTemplateService;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Services\PhoneNormalizer;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class LoanBillingNoticeReportService
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly PhoneNormalizer $phoneNormalizer,
        private readonly SignatureTemplateService $signatures,
        private readonly SignatureImageService $signatureImages,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period: array{period_label: string, year: int, month: int},
     *   identity: array{legal_name: string, short_name: ?string, district_name: ?string, regency_name: ?string, address: ?string, phone: ?string, logo_url: ?string},
     *   filters: array{village_row_id: ?int, group_row_id: ?int, only_due: bool},
     *   groups: list<array{
     *     group_name: string,
     *     village_name: string,
     *     loans: list<array{
     *       loan_row_id: int,
     *       loan_number: string,
     *       group_name: string,
     *       village_name: string,
     *       items: list<array{
     *         beneficiary_name: string,
     *         installment_number: int,
     *         due_date: string,
     *         principal_due: float,
     *         interest_due: float,
     *         penalty_due: float,
     *         total_due: float,
     *         phone: ?string,
     *         status: string,
     *         loan_number: string,
     *         group_name: string,
     *         village_name: string
     *       }>,
     *       totals: array{members_count: int, principal: float, interest: float, penalty: float, total: float}
     *     }>,
     *     totals: array{members_count: int, principal: float, interest: float, penalty: float, total: float}
     *   }>,
     *   totals: array{members_count: int, loans_count: int, groups_count: int, principal: float, interest: float, penalty: float, total: float},
     *   signature: string
     * }
     */
    public function build(
        int $year,
        int $month,
        ?int $villageRowId = null,
        ?int $groupRowId = null,
        bool $onlyDue = true,
    ): array {
        $tenantId = $this->context->id();
        $startOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth()->toDateString();
        $endOfMonth = CarbonImmutable::createFromDate($year, $month, 1)->endOfMonth()->toDateString();

        $query = DB::connection('tenant')
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
            ->leftJoin('organization_units as v', function ($j): void {
                $j->on('v.tenant_id', '=', 'g.tenant_id')
                    ->on('v.row_id', '=', 'g.organization_unit_row_id');
            })
            ->where('i.tenant_id', $tenantId)
            ->whereIn('l.status', ['active', 'disbursed'])
            ->whereBetween('i.due_date', [$startOfMonth, $endOfMonth]);

        if ($villageRowId !== null && $villageRowId > 0) {
            $query->where('v.row_id', $villageRowId);
        }

        if ($groupRowId !== null && $groupRowId > 0) {
            $query->where('g.row_id', $groupRowId);
        }

        if ($onlyDue) {
            $query->whereIn('i.status', ['pending', 'overdue', 'partial'])
                ->whereRaw('(i.principal_due + i.interest_due + i.penalty_due) > (i.principal_paid + i.interest_paid + i.penalty_paid)');
        }

        $installments = $query->orderBy('v.name')
            ->orderBy('g.name')
            ->orderBy('l.id')
            ->orderBy('i.installment_number')
            ->get([
                'i.row_id as installment_row_id',
                'i.installment_number',
                'i.due_date',
                'i.status as installment_status',
                'i.principal_due',
                'i.interest_due',
                'i.penalty_due',
                'i.principal_paid',
                'i.interest_paid',
                'i.penalty_paid',
                'l.row_id as loan_row_id',
                'l.id as loan_id',
                'l.loan_number',
                'l.principal_amount as loan_principal_amount',
                'g.row_id as group_row_id',
                'g.name as group_name',
                'g.phone as group_phone',
                'v.row_id as village_row_id',
                'v.name as village_name',
            ]);

        $loanRowIds = $installments->pluck('loan_row_id')->unique()->filter()->values()->all();

        $beneficiaries = $loanRowIds === []
            ? collect()
            : DB::connection('tenant')
                ->table('loan_beneficiaries as lb')
                ->join('members as m', function ($j): void {
                    $j->on('m.tenant_id', '=', 'lb.tenant_id')
                        ->on('m.row_id', '=', 'lb.member_row_id');
                })
                ->join('people as p', function ($j): void {
                    $j->on('p.tenant_id', '=', 'm.tenant_id')
                        ->on('p.row_id', '=', 'm.person_row_id');
                })
                ->where('lb.tenant_id', $tenantId)
                ->whereIn('lb.loan_row_id', $loanRowIds)
                ->get([
                    'lb.loan_row_id',
                    'lb.member_row_id',
                    'lb.allocated_amount',
                    'p.full_name as member_name',
                    'p.phone as member_phone',
                ])
                ->groupBy('loan_row_id');

        $groupsAcc = [];
        $grandTotals = [
            'members_count' => 0,
            'loans_count' => count($loanRowIds),
            'groups_count' => 0,
            'principal' => 0.0,
            'interest' => 0.0,
            'penalty' => 0.0,
            'total' => 0.0,
        ];

        foreach ($installments as $inst) {
            $loanId = (int) $inst->loan_row_id;
            $groupKey = (string) ($inst->group_name ?: 'Tanpa Kelompok');
            $villageName = (string) ($inst->village_name ?: '—');
            $loanNumber = (string) ($inst->loan_number ?: ('#'.$inst->loan_id));

            if (! isset($groupsAcc[$groupKey])) {
                $groupsAcc[$groupKey] = [
                    'group_name' => $groupKey,
                    'village_name' => $villageName,
                    'loans' => [],
                    'totals' => [
                        'members_count' => 0,
                        'principal' => 0.0,
                        'interest' => 0.0,
                        'penalty' => 0.0,
                        'total' => 0.0,
                    ],
                ];
            }

            if (! isset($groupsAcc[$groupKey]['loans'][$loanId])) {
                $groupsAcc[$groupKey]['loans'][$loanId] = [
                    'loan_row_id' => $loanId,
                    'loan_number' => $loanNumber,
                    'group_name' => $groupKey,
                    'village_name' => $villageName,
                    'items' => [],
                    'totals' => [
                        'members_count' => 0,
                        'principal' => 0.0,
                        'interest' => 0.0,
                        'penalty' => 0.0,
                        'total' => 0.0,
                    ],
                ];
            }

            $pDue = max(0.0, (float) $inst->principal_due - (float) $inst->principal_paid);
            $iDue = max(0.0, (float) $inst->interest_due - (float) $inst->interest_paid);
            $penDue = max(0.0, (float) $inst->penalty_due - (float) $inst->penalty_paid);
            $totalDue = round($pDue + $iDue + $penDue, 2);

            $loanBens = $beneficiaries->get($loanId) ?? collect();
            $totalLoanAllocated = (float) $loanBens->sum('allocated_amount');

            if ($loanBens->isNotEmpty() && $totalLoanAllocated > 0) {
                foreach ($loanBens as $ben) {
                    $ratio = (float) $ben->allocated_amount / $totalLoanAllocated;
                    $benPrincipal = round($pDue * $ratio, 2);
                    $benInterest = round($iDue * $ratio, 2);
                    $benPenalty = round($penDue * $ratio, 2);
                    $benTotal = round($benPrincipal + $benInterest + $benPenalty, 2);

                    $phone = $this->formatPhone((string) ($ben->member_phone ?: $inst->group_phone ?: ''));

                    $item = [
                        'beneficiary_name' => (string) $ben->member_name,
                        'installment_number' => (int) $inst->installment_number,
                        'due_date' => (string) $inst->due_date,
                        'principal_due' => $benPrincipal,
                        'interest_due' => $benInterest,
                        'penalty_due' => $benPenalty,
                        'total_due' => $benTotal,
                        'phone' => $phone,
                        'status' => (string) $inst->installment_status,
                        'loan_number' => $loanNumber,
                        'group_name' => $groupKey,
                        'village_name' => $villageName,
                    ];

                    $groupsAcc[$groupKey]['loans'][$loanId]['items'][] = $item;
                    $groupsAcc[$groupKey]['loans'][$loanId]['totals']['members_count']++;
                    $groupsAcc[$groupKey]['loans'][$loanId]['totals']['principal'] += $benPrincipal;
                    $groupsAcc[$groupKey]['loans'][$loanId]['totals']['interest'] += $benInterest;
                    $groupsAcc[$groupKey]['loans'][$loanId]['totals']['penalty'] += $benPenalty;
                    $groupsAcc[$groupKey]['loans'][$loanId]['totals']['total'] += $benTotal;

                    $groupsAcc[$groupKey]['totals']['members_count']++;
                    $groupsAcc[$groupKey]['totals']['principal'] += $benPrincipal;
                    $groupsAcc[$groupKey]['totals']['interest'] += $benInterest;
                    $groupsAcc[$groupKey]['totals']['penalty'] += $benPenalty;
                    $groupsAcc[$groupKey]['totals']['total'] += $benTotal;

                    $grandTotals['members_count']++;
                    $grandTotals['principal'] += $benPrincipal;
                    $grandTotals['interest'] += $benInterest;
                    $grandTotals['penalty'] += $benPenalty;
                    $grandTotals['total'] += $benTotal;
                }
            } else {
                $phone = $this->formatPhone((string) ($inst->group_phone ?: ''));
                $item = [
                    'beneficiary_name' => $groupKey,
                    'installment_number' => (int) $inst->installment_number,
                    'due_date' => (string) $inst->due_date,
                    'principal_due' => $pDue,
                    'interest_due' => $iDue,
                    'penalty_due' => $penDue,
                    'total_due' => $totalDue,
                    'phone' => $phone,
                    'status' => (string) $inst->installment_status,
                    'loan_number' => $loanNumber,
                    'group_name' => $groupKey,
                    'village_name' => $villageName,
                ];

                $groupsAcc[$groupKey]['loans'][$loanId]['items'][] = $item;
                $groupsAcc[$groupKey]['loans'][$loanId]['totals']['members_count']++;
                $groupsAcc[$groupKey]['loans'][$loanId]['totals']['principal'] += $pDue;
                $groupsAcc[$groupKey]['loans'][$loanId]['totals']['interest'] += $iDue;
                $groupsAcc[$groupKey]['loans'][$loanId]['totals']['penalty'] += $penDue;
                $groupsAcc[$groupKey]['loans'][$loanId]['totals']['total'] += $totalDue;

                $groupsAcc[$groupKey]['totals']['members_count']++;
                $groupsAcc[$groupKey]['totals']['principal'] += $pDue;
                $groupsAcc[$groupKey]['totals']['interest'] += $iDue;
                $groupsAcc[$groupKey]['totals']['penalty'] += $penDue;
                $groupsAcc[$groupKey]['totals']['total'] += $totalDue;

                $grandTotals['members_count']++;
                $grandTotals['principal'] += $pDue;
                $grandTotals['interest'] += $iDue;
                $grandTotals['penalty'] += $penDue;
                $grandTotals['total'] += $totalDue;
            }
        }

        $grandTotals['groups_count'] = count($groupsAcc);

        $outGroups = [];
        foreach ($groupsAcc as $g) {
            $g['loans'] = array_values($g['loans']);
            $outGroups[] = $g;
        }

        $profile = OrganizationProfile::query()->first();
        $monthNames = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];

        $signatureHtml = $this->renderSignature();

        return [
            'year' => $year,
            'month' => $month,
            'period' => [
                'period_label' => ($monthNames[$month] ?? "Bulan {$month}")." {$year}",
                'year' => $year,
                'month' => $month,
            ],
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? config('app.name')),
                'short_name' => $profile?->short_name,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
                'address' => $profile?->address,
                'phone' => $profile?->phone,
                'logo_url' => $profile?->logo_url,
            ],
            'filters' => [
                'village_row_id' => $villageRowId,
                'group_row_id' => $groupRowId,
                'only_due' => $onlyDue,
            ],
            'groups' => $outGroups,
            'totals' => $grandTotals,
            'signature' => $signatureHtml,
        ];
    }

    private function formatPhone(string $phone): ?string
    {
        $normalized = $this->phoneNormalizer->normalize($phone);
        if ($normalized === '') {
            return null;
        }

        return '+'.$normalized;
    }

    private function renderSignature(): string
    {
        $html = $this->signatures->get('kwitansi');
        if ($html === '') {
            $html = $this->signatures->get('default');
        }

        if ($html === '') {
            return '';
        }

        $uri = $this->signatureImages->dataUri('kwitansi');
        if ($uri === null) {
            return $html;
        }

        $img = '<img src="'.$uri.'" style="height:50px;max-width:180px;object-fit:contain" alt="Tanda Tangan" />';
        if (str_contains($html, '{ttd_image}')) {
            return str_replace('{ttd_image}', $img, $html);
        }

        return preg_replace('/(<p>(<br\s*\/?>\s*)+<\/p>)/i', $img.'$1', $html, 1) ?? $html;
    }
}

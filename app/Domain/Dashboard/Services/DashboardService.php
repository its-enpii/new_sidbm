<?php

declare(strict_types=1);

namespace App\Domain\Dashboard\Services;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan operasional tenant — KPI live dari journal + lending.
 */
final class DashboardService
{
    private const CASH_PREFIX = '1.1.01';

    private const ACTIVE_LOAN_STATUSES = ['active', 'disbursed'];

    private const PIPELINE = [
        'draft' => 'Proposal',
        'verified' => 'Verifikasi',
        'waiting' => 'Menunggu cair',
        'active' => 'Aktif',
    ];

    public function __construct(
        private readonly TenantContext $context,
    ) {
    }

    private function tenantId(): int
    {
        return $this->context->id();
    }

    /**
     * @return array{
     *     unit_name: ?string,
     *     as_of: string,
     *     cards: list<array{key:string,label:string,icon:string,value:float|int,format:string,hint:?string,tone:?string}>,
     *     pipeline: list<array{status:string,label:string,count:int,amount:float}>,
     *     trend: list<array{key:string,label:string,disbursed:float,collected:float}>,
     *     recent_journals: list<array{row_id:int,journal_number:?string,transaction_date:string,description:?string,amount:float,source_type:?string}>,
     *     upcoming_due: list<array{row_id:int,due_date:string,loan_number:?string,borrower:string,amount:float,status:string,overdue:bool}>,
     *     overdue_summary: array{count:int,amount:float},
     *     counts: array{members:int,groups:int,active_loans:int}
     * }
     */
    public function build(): array
    {
        $today = CarbonImmutable::today();
        $profile = OrganizationProfile::query()->first(['short_name', 'legal_name']);

        $cashBalance = $this->cashBalance();
        $activeOutstanding = $this->activeOutstanding();
        $overdue = $this->overdueSummary($today);
        $memberCount = (int) Member::query()->where('status', 'active')->count();
        $groupCount = (int) Group::query()->where('status', 'active')->count();
        $activeLoanCount = (int) Loan::query()->whereIn('status', self::ACTIVE_LOAN_STATUSES)->count();

        return [
            'unit_name' => $profile?->short_name ?: $profile?->legal_name,
            'as_of' => $today->toDateString(),
            'cards' => [
                [
                    'key' => 'cash',
                    'label' => 'Saldo Kas/Bank',
                    'icon' => 'account_balance',
                    'value' => $cashBalance,
                    'format' => 'money',
                    'hint' => 'Akun 1.1.01* (posted)',
                    'tone' => null,
                ],
                [
                    'key' => 'outstanding',
                    'label' => 'Outstanding Pokok',
                    'icon' => 'payments',
                    'value' => $activeOutstanding['amount'],
                    'format' => 'money',
                    'hint' => $activeOutstanding['count'].' pinjaman aktif',
                    'tone' => null,
                ],
                [
                    'key' => 'overdue',
                    'label' => 'Tunggakan',
                    'icon' => 'warning',
                    'value' => $overdue['amount'],
                    'format' => 'money',
                    'hint' => $overdue['count'].' angsuran lewat jatuh tempo',
                    'tone' => $overdue['count'] > 0 ? 'error' : null,
                ],
                [
                    'key' => 'members',
                    'label' => 'Anggota Aktif',
                    'icon' => 'groups',
                    'value' => $memberCount,
                    'format' => 'number',
                    'hint' => $groupCount.' kelompok aktif',
                    'tone' => null,
                ],
            ],
            'pipeline' => $this->pipeline(),
            'trend' => $this->monthlyTrend($today),
            'recent_journals' => $this->recentJournals(),
            'upcoming_due' => $this->upcomingDue($today),
            'overdue_summary' => $overdue,
            'counts' => [
                'members' => $memberCount,
                'groups' => $groupCount,
                'active_loans' => $activeLoanCount,
            ],
        ];
    }

    private function cashBalance(): float
    {
        $tenantId = $this->tenantId();

        $cashIds = DB::connection('tenant')
            ->table('accounts')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->where('code', 'like', self::CASH_PREFIX.'%')
            ->pluck('row_id');

        if ($cashIds->isEmpty()) {
            return 0.0;
        }

        $year = (int) date('Y');

        $opening = (float) DB::connection('tenant')
            ->table('account_opening_balances')
            ->where('tenant_id', $tenantId)
            ->whereIn('account_row_id', $cashIds)
            ->where('fiscal_year', $year)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS bal')
            ->value('bal');

        $yearStart = sprintf('%04d-01-01', $year);

        $movement = (float) DB::connection('tenant')
            ->table('journal_lines as lines')
            ->join('journal_entries as entries', function ($join): void {
                $join->on('entries.tenant_id', '=', 'lines.tenant_id')
                    ->on('entries.row_id', '=', 'lines.journal_entry_row_id');
            })
            ->where('lines.tenant_id', $tenantId)
            ->where('entries.status', 'posted')
            ->where('entries.transaction_date', '>=', $yearStart)
            ->whereIn('lines.account_row_id', $cashIds)
            ->selectRaw('COALESCE(SUM(lines.debit - lines.credit), 0) AS bal')
            ->value('bal');

        return round($opening + $movement, 2);
    }

    /**
     * @return array{count:int,amount:float}
     */
    private function activeOutstanding(): array
    {
        $row = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($join): void {
                $join->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
            ->where('i.tenant_id', $this->tenantId())
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->selectRaw('COUNT(DISTINCT l.row_id) AS loan_count')
            ->selectRaw('COALESCE(SUM(i.principal_due - i.principal_paid), 0) AS outstanding')
            ->first();

        return [
            'count' => (int) ($row->loan_count ?? 0),
            'amount' => round((float) ($row->outstanding ?? 0), 2),
        ];
    }

    /**
     * @return array{count:int,amount:float}
     */
    private function overdueSummary(CarbonImmutable $today): array
    {
        $row = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($join): void {
                $join->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
            ->where('i.tenant_id', $this->tenantId())
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->where('i.due_date', '<', $today->toDateString())
            ->whereRaw('(i.principal_due + i.interest_due + i.penalty_due) > (i.principal_paid + i.interest_paid + i.penalty_paid)')
            ->selectRaw('COUNT(*) AS cnt')
            ->selectRaw('COALESCE(SUM((i.principal_due + i.interest_due + i.penalty_due) - (i.principal_paid + i.interest_paid + i.penalty_paid)), 0) AS amt')
            ->first();

        return [
            'count' => (int) ($row->cnt ?? 0),
            'amount' => round((float) ($row->amt ?? 0), 2),
        ];
    }

    /**
     * @return list<array{status:string,label:string,count:int,amount:float}>
     */
    private function pipeline(): array
    {
        $rows = Loan::query()
            ->whereIn('status', array_keys(self::PIPELINE))
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(principal_amount), 0) as amt')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $out = [];
        foreach (self::PIPELINE as $status => $label) {
            $row = $rows->get($status);
            $out[] = [
                'status' => $status,
                'label' => $label,
                'count' => (int) ($row->cnt ?? 0),
                'amount' => round((float) ($row->amt ?? 0), 2),
            ];
        }

        return $out;
    }

    /**
     * @return list<array{key:string,label:string,disbursed:float,collected:float}>
     */
    private function monthlyTrend(CarbonImmutable $today): array
    {
        $start = $today->startOfMonth()->subMonths(5);
        $labels = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
            7 => 'Jul', 8 => 'Agu', 9 => 'Sep', 10 => 'Okt', 11 => 'Nov', 12 => 'Des',
        ];

        $tenantId = $this->tenantId();

        $disbursed = DB::connection('tenant')
            ->table('loans')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [...self::ACTIVE_LOAN_STATUSES, 'completed', 'written_off', 'rescheduled'])
            ->whereNotNull('disbursed_at')
            ->where('disbursed_at', '>=', $start->toDateString())
            ->selectRaw('YEAR(disbursed_at) as y, MONTH(disbursed_at) as m')
            ->selectRaw('COALESCE(SUM(principal_amount), 0) as total')
            ->groupByRaw('YEAR(disbursed_at), MONTH(disbursed_at)')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));

        $collected = DB::connection('tenant')
            ->table('loan_payments')
            ->where('tenant_id', $tenantId)
            ->where('paid_at', '>=', $start->toDateTimeString())
            ->selectRaw('YEAR(paid_at) as y, MONTH(paid_at) as m')
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->groupByRaw('YEAR(paid_at), MONTH(paid_at)')
            ->get()
            ->keyBy(fn ($r) => sprintf('%04d-%02d', $r->y, $r->m));

        $trend = [];
        for ($i = 0; $i < 6; $i++) {
            $month = $start->addMonths($i);
            $key = $month->format('Y-m');
            $trend[] = [
                'key' => $key,
                'label' => ($labels[(int) $month->month] ?? $month->format('M')).' '.$month->format('y'),
                'disbursed' => round((float) ($disbursed->get($key)?->total ?? 0), 2),
                'collected' => round((float) ($collected->get($key)?->total ?? 0), 2),
            ];
        }

        return $trend;
    }

    /**
     * @return list<array{row_id:int,journal_number:?string,transaction_date:string,description:?string,amount:float,source_type:?string}>
     */
    private function recentJournals(): array
    {
        $entries = JournalEntry::query()
            ->where('status', 'posted')
            ->orderByDesc('transaction_date')
            ->orderByDesc('row_id')
            ->limit(8)
            ->get(['row_id', 'journal_number', 'transaction_date', 'description', 'source_type']);

        if ($entries->isEmpty()) {
            return [];
        }

        $ids = $entries->pluck('row_id')->all();
        $totals = DB::connection('tenant')
            ->table('journal_lines')
            ->where('tenant_id', $this->tenantId())
            ->whereIn('journal_entry_row_id', $ids)
            ->selectRaw('journal_entry_row_id')
            ->selectRaw('COALESCE(SUM(debit), 0) as debit_total')
            ->groupBy('journal_entry_row_id')
            ->get()
            ->keyBy('journal_entry_row_id');

        return $entries->map(function (JournalEntry $entry) use ($totals): array {
            return [
                'row_id' => (int) $entry->row_id,
                'journal_number' => $entry->journal_number,
                'transaction_date' => $entry->transaction_date?->toDateString() ?? '',
                'description' => $entry->description,
                'amount' => round((float) ($totals->get($entry->row_id)?->debit_total ?? 0), 2),
                'source_type' => $entry->source_type,
            ];
        })->all();
    }

    /**
     * @return list<array{row_id:int,due_date:string,loan_number:?string,borrower:string,amount:float,status:string,overdue:bool}>
     */
    private function upcomingDue(CarbonImmutable $today): array
    {
        $until = $today->addDays(14)->toDateString();

        $rows = DB::connection('tenant')
            ->table('loan_installments as i')
            ->join('loans as l', function ($join): void {
                $join->on('l.tenant_id', '=', 'i.tenant_id')
                    ->on('l.row_id', '=', 'i.loan_row_id');
            })
            ->leftJoin('loan_borrowers as b', function ($join): void {
                $join->on('b.tenant_id', '=', 'l.tenant_id')
                    ->on('b.loan_row_id', '=', 'l.row_id');
            })
            ->leftJoin('groups as g', function ($join): void {
                $join->on('g.tenant_id', '=', 'b.tenant_id')
                    ->on('g.row_id', '=', 'b.group_row_id');
            })
            ->leftJoin('members as m', function ($join): void {
                $join->on('m.tenant_id', '=', 'b.tenant_id')
                    ->on('m.row_id', '=', 'b.member_row_id');
            })
            ->leftJoin('people as p', function ($join): void {
                $join->on('p.tenant_id', '=', 'm.tenant_id')
                    ->on('p.row_id', '=', 'm.person_row_id');
            })
            ->where('i.tenant_id', $this->tenantId())
            ->whereIn('l.status', self::ACTIVE_LOAN_STATUSES)
            ->where('i.due_date', '<=', $until)
            ->whereRaw('(i.principal_due + i.interest_due + i.penalty_due) > (i.principal_paid + i.interest_paid + i.penalty_paid)')
            ->orderBy('i.due_date')
            ->limit(10)
            ->get([
                'i.row_id',
                'i.due_date',
                'i.status',
                'l.loan_number',
                'g.name as group_name',
                'p.full_name as member_name',
                DB::raw('((i.principal_due + i.interest_due + i.penalty_due) - (i.principal_paid + i.interest_paid + i.penalty_paid)) as remaining'),
            ]);

        return $rows->map(function ($row) use ($today): array {
            $due = (string) $row->due_date;

            return [
                'row_id' => (int) $row->row_id,
                'due_date' => $due,
                'loan_number' => $row->loan_number,
                'borrower' => (string) ($row->group_name ?: $row->member_name ?: '—'),
                'amount' => round((float) $row->remaining, 2),
                'status' => (string) $row->status,
                'overdue' => $due < $today->toDateString(),
            ];
        })->all();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Lending\Services\Reports;

use App\Domain\Lending\Models\Loan;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;

/**
 * Daftar Pinjaman Tidak Layak (Kelompok).
 *
 * Menyajikan daftar kelompok pemanfaat yang dinyatakan tidak layak didanai/dicairkan.
 * Pinjaman dianggap tidak layak bila berstatus `unfeasible` / `tidak_layak`, atau
 * berstatus `draft` / `rejected` yang memiliki catatan/riwayat berisi 'tidak layak'.
 * Data dikelompokkan per Desa mengikuti format legacy (`tidak_layak.blade.php`).
 */
final class UnfeasibleLoanReportService
{
    /** Status pinjaman yang secara eksplisit dinyatakan tidak layak. */
    private const UNFEASIBLE_STATUSES = ['unfeasible', 'tidak_layak'];

    /** Status yang dapat ditandai tidak layak melalui catatan/riwayat. */
    private const NOTE_SOURCE_STATUSES = ['draft', 'rejected'];

    private const HOLDING_STATUSES = ['draft', 'rejected'];

    public function __construct(
        private readonly TenantContext $context,
    ) {}

    /**
     * @return array{
     *   year: int,
     *   month: int,
     *   period_label: string,
     *   identity: array{legal_name: string, short_name: ?string, district_name: string},
     *   products: list<array<string, mixed>>,
     *   villages: list<array<string, mixed>>,
     *   totals: array{loans_count: int, groups_count: int, members_count: int, amount: float}
     * }
     */
    public function build(int $year, int $month, ?string $productCode = null): array
    {
        $productCode = $productCode === 'all' ? null : $productCode;

        $profile = OrganizationProfile::query()->first();

        $query = Loan::query()
            ->where(function ($q): void {
                $q->whereIn('status', self::UNFEASIBLE_STATUSES)
                    ->orWhere(function ($inner): void {
                        $inner->whereIn('status', self::NOTE_SOURCE_STATUSES)
                            ->where(function ($note): void {
                                $note->where('verification_notes', 'like', '%tidak layak%')
                                    ->orWhere('guidance_notes', 'like', '%tidak layak%')
                                    ->orWhereHas('statusHistories', function ($history): void {
                                        $history->where('notes', 'like', '%tidak layak%')
                                            ->orWhereIn('to_status', self::UNFEASIBLE_STATUSES);
                                    });
                            });
                    });
            })
            ->with([
                'product:row_id,code,name,borrower_scope',
                'borrower.group:row_id,name,code,address,organization_unit_row_id',
                'borrower.group.village:row_id,name,code',
                'beneficiaries.member:row_id,person_row_id,member_number',
                'beneficiaries.member.person:row_id,full_name',
                'statusHistories' => fn ($q) => $q->orderByDesc('changed_at'),
            ]);

        if ($productCode !== null && $productCode !== '') {
            $query->whereHas('product', fn ($q) => $q->where('code', $productCode));
        }

        $loans = $query
            ->orderBy('id')
            ->get();

        $villagesMap = [];
        $productsMap = [];
        $totalLoans = 0;
        $totalGroups = 0;
        $totalMembers = 0;
        $totalAmount = 0.0;

        foreach ($loans as $loan) {
            $group = $loan->borrower?->group;
            $village = $group?->village;

            $villageKey = (string) ($village?->code ?? $village?->name ?? 'LAIN-LAIN');
            if (! isset($villagesMap[$villageKey])) {
                $villagesMap[$villageKey] = [
                    'kode_desa' => (string) ($village?->code ?? '-'),
                    'nama_desa' => (string) ($village?->name ?? 'Lain-lain'),
                    'loans' => [],
                    'subtotal' => [
                        'loans_count' => 0,
                        'groups_count' => 0,
                        'members_count' => 0,
                        'amount' => 0.0,
                    ],
                ];
            }

            $members = $loan->beneficiaries
                ->map(fn ($beneficiary) => [
                    'member_number' => $beneficiary->member?->member_number,
                    'name' => $beneficiary->member?->person?->full_name ?? $beneficiary->member?->member_number ?? '-',
                    'allocated_amount' => (float) ($beneficiary->allocated_amount ?? $beneficiary->verified_amount ?? 0),
                ])
                ->values()
                ->all();

            $membersCount = max(1, count($members));
            $amount = (float) $loan->principal_amount;
            $unfeasibleAt = $this->resolveUnfeasibleDate($loan);

            $row = [
                'loan_id' => (int) $loan->id,
                'loan_row_id' => (int) $loan->row_id,
                'loan_number' => (string) ($loan->loan_number ?? '—'),
                'status' => (string) $loan->status,
                'group_name' => (string) ($group?->name ?? 'Individu'),
                'group_code' => $group?->code,
                'group_address' => $this->resolveAddress($group?->address, $village?->name),
                'village_name' => (string) ($village?->name ?? 'Lain-lain'),
                'village_code' => (string) ($village?->code ?? '-'),
                'product_code' => $loan->product?->code,
                'borrower_scope' => (string) ($loan->product?->borrower_scope ?? 'group'),
                'jenis_pinjaman' => $this->resolveJenisPinjaman($loan->product?->borrower_scope),
                'product_name' => $loan->product?->name,
                'unfeasible_at' => $unfeasibleAt,
                'waiting_since' => $loan->proposed_at?->format('Y-m-d'),
                'members_count' => $membersCount,
                'amount' => $amount,
                'members' => $members,
            ];

            $villagesMap[$villageKey]['loans'][] = $row;
            $villagesMap[$villageKey]['subtotal']['loans_count'] += 1;
            $villagesMap[$villageKey]['subtotal']['groups_count'] += 1;
            $villagesMap[$villageKey]['subtotal']['members_count'] += $membersCount;
            $villagesMap[$villageKey]['subtotal']['amount'] += $amount;

            $productKey = (string) ($loan->product?->code ?? 'LAIN-LAIN');
            if (! isset($productsMap[$productKey])) {
                $productsMap[$productKey] = [
                    'product_code' => $productKey,
                    'product_name' => (string) ($loan->product?->name ?? 'Lain-lain'),
                    'loans_count' => 0,
                    'amount' => 0.0,
                ];
            }
            $productsMap[$productKey]['loans_count'] += 1;
            $productsMap[$productKey]['amount'] += $amount;

            $totalLoans += 1;
            $totalGroups += 1;
            $totalMembers += $membersCount;
            $totalAmount += $amount;
        }

        ksort($villagesMap);

        return [
            'year' => $year,
            'month' => $month,
            'period_label' => sprintf('%s %04d', $this->monthName($month), $year),
            'identity' => [
                'legal_name' => (string) ($profile?->legal_name ?? 'BUMDesma LKD'),
                'short_name' => $profile?->short_name,
                'district_name' => (string) ($profile?->district_name ?? ''),
            ],
            'products' => array_values($productsMap),
            'villages' => array_values($villagesMap),
            'totals' => [
                'loans_count' => $totalLoans,
                'groups_count' => $totalGroups,
                'members_count' => $totalMembers,
                'amount' => round($totalAmount, 2),
            ],
        ];
    }

    private function resolveJenisPinjaman(?string $borrowerScope): string
    {
        return match (strtolower((string) $borrowerScope)) {
            'member' => '',
            'group', 'both' => 'Kelompok',
            default => 'Kelompok',
        };
    }

    private function resolveUnfeasibleDate(Loan $loan): ?string
    {
        if (in_array((string) $loan->status, self::UNFEASIBLE_STATUSES, true)) {
            $history = $loan->statusHistories
                ->first(fn ($h) => in_array((string) $h->to_status, self::UNFEASIBLE_STATUSES, true));

            return $history?->changed_at?->format('Y-m-d');
        }

        return $loan->proposed_at?->format('Y-m-d');
    }

    private function resolveAddress(?string $address, ?string $villageName): string
    {
        $address = trim((string) $address);
        if ($address !== '') {
            return $address;
        }

        return (string) ($villageName ?? '');
    }

    private function monthName(int $month): string
    {
        return [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ][$month] ?? "Bulan {$month}";
    }
}

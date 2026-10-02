<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Services\Reports\CalkService;
use App\Domain\Accounting\Services\Reports\CashFlowService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Services\Reports\LoanScheduleVsActualService;
use App\Domain\Lending\Services\Reports\LppReportService;
use App\Domain\Lending\Services\Reports\UnfeasibleLoanReportService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

/**
 * Verifikasi bahwa seluruh "gap" yang dulu dirender kosong kini terisi dari JOIN nyata:
 * ketua kelompok (group_officers), pengajuan vs pencairan (loan_beneficiaries.proposed_amount),
 * status lunas/reschedule/hapus buku (loans.status), jenis pinjaman (loan_products.borrower_scope),
 * distribusi laba per desa (organization_units + jurnal ekuitas), dan label Saldo Awal.
 */
final class ReportDomainJoinTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private int $productId;

    private OrganizationUnit $village;

    private Group $group;

    private Member $memberK;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->village = OrganizationUnit::query()->create([
            'id' => 1, 'code' => 'V001', 'name' => 'Desa Sukamaju', 'type' => 'village',
            'is_active' => true, 'leader_name' => 'H. Sukarno', 'responsible_name' => 'Hj. Kartini',
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-01', 'name' => 'Kelompok Mawar', 'status' => 'active',
            'address' => 'Dusun Krajan No. 1', 'organization_unit_row_id' => $this->village->row_id,
        ]);

        $personK = Person::query()->create([
            'national_identity_number' => '3507010101900011', 'full_name' => 'Ketua Siti', 'gender' => 'P',
        ]);
        $this->memberK = Member::query()->create([
            'person_row_id' => $personK->row_id, 'organization_unit_row_id' => $this->village->row_id,
            'member_number' => 'MBR-K01', 'registered_at' => '2026-01-01', 'status' => 'active',
        ]);
        Person::query()->create([
            'national_identity_number' => '3507010101900012', 'full_name' => 'Anggota Budi', 'gender' => 'L',
        ]);
        $personB = Person::query()->latest('row_id')->first();
        Member::query()->create([
            'person_row_id' => $personB->row_id, 'organization_unit_row_id' => $this->village->row_id,
            'member_number' => 'MBR-A01', 'registered_at' => '2026-01-01', 'status' => 'active',
        ]);

        // group_officers: ketua = memberK. Position legacy memakai 'chair' (lihat LegacyMembershipNormalizer)
        // sekaligus siap untuk 'ketua'.
        DB::connection('tenant')->table('group_officers')->insert([
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => $this->memberK->row_id,
            'position' => 'chair',
            'started_at' => '2026-01-01',
            'ended_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_real_db_query_evidence(): void
    {
        $this->seedLoan('2026-01-15', 1000000, 1000000, 800000, 'active', proposedAt: '2025-12-01');
        $this->seedSimpleLoan(2000000, '2026-02-10', 'completed', '2026-08-20');
        $this->seedSimpleLoan(3000000, '2026-03-10', 'rescheduled', '2026-09-01');
        $this->seedSimpleLoan(4000000, '2026-04-10', 'written_off', '2026-09-05');

        $c = DB::connection('tenant');

        echo "\n=== RAW DB EVIDENCE ===\n";
        echo 'group_officers ketua/chair (tenant '.$this->testTenant->row_id.'): '
            .$c->table('group_officers')
                ->whereRaw("LOWER(position) IN ('ketua','chief','chair')")->count()."\n";
        echo 'loan_beneficiaries proposed_amount NOT NULL: '
            .$c->table('loan_beneficiaries')->whereNotNull('proposed_amount')->count()."\n";
        echo 'loan_beneficiaries total: '.$c->table('loan_beneficiaries')->count()."\n";
        echo 'loans.status distribution: '
            .json_encode($c->table('loans')->selectRaw('status, COUNT(*) c')->groupBy('status')->orderBy('status')->pluck('c', 'status'))."\n";
        echo 'loan_products borrower_scope: '
            .json_encode($c->table('loan_products')->select('code', 'borrower_scope')->orderBy('code')->get()->toArray())."\n";
        echo 'organization_units villages: '
            .json_encode($c->table('organization_units')->where('type', 'village')->select('code', 'name')->get()->toArray())."\n";

        $this->assertGreaterThan(0, $c->table('group_officers')
            ->whereRaw("LOWER(position) IN ('ketua','chief','chair')")->count());
        $this->assertGreaterThan(0, $c->table('loan_beneficiaries')->whereNotNull('proposed_amount')->count());
    }

    public function test_lpp_kelompok_renders_ketua_and_status_rows(): void
    {
        $this->seedSimpleLoan(2000000, '2026-02-10', 'completed', '2026-08-20', proposedAt: '2026-01-05');

        $data = app(LppReportService::class)->buildKelompok(2026, 9);
        $loans = collect($data['products'])->flatMap(fn ($p) => collect($p['villages'])->flatMap(fn ($v) => $v['loans']))->all();

        self::assertNotEmpty($loans);
        self::assertSame('Ketua Siti', $loans[0]['ketua']);
        self::assertSame('completed', $loans[0]['status']);
        self::assertSame('2026-08-20', $loans[0]['tgl_lunas']);

        $html = view('reports.pdf.lending.lpp_kelompok', $data + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Ketua Siti', $html);
        self::assertStringContainsString('V-LUNAS 2026-08-20', $html);

        $bytes = Pdf::loadView('reports.pdf.lending.lpp_kelompok', $data + ['tanda_tangan' => ''])->setPaper('a4', 'landscape')->output();
        self::assertStringStartsWith('%PDF', $bytes);
        self::assertGreaterThan(2000, strlen($bytes));
    }

    public function test_lpp_kelompok_renders_reschedule_and_writeoff_markers(): void
    {
        $this->seedSimpleLoan(3000000, '2026-03-10', 'rescheduled', '2026-09-01');
        $this->seedSimpleLoan(4000000, '2026-04-10', 'written_off', '2026-09-05');

        $data = app(LppReportService::class)->buildKelompok(2026, 9);
        $html = view('reports.pdf.lending.lpp_kelompok', $data + ['tanda_tangan' => ''])->render();

        self::assertStringContainsString('Rescedulling 2026-09-01', $html);
        self::assertStringContainsString('Penghapusan 2026-09-05', $html);

        $desa = app(LppReportService::class)->buildDesa(2026, 9);
        $desaHtml = view('reports.pdf.lending.lpp_desa', $desa + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Desa Sukamaju', $desaHtml);

        $pdf = Pdf::loadView('reports.pdf.lending.lpp_desa', $desa + ['tanda_tangan' => ''])->setPaper('a4', 'landscape')->output();
        self::assertStringStartsWith('%PDF', $pdf);
    }

    public function test_lpp_kelompok_ketua_fallback_dash_when_missing(): void
    {
        // Kelompok tanpa officer.
        $group2 = Group::query()->create([
            'code' => 'KLP-02', 'name' => 'Kelompok Melati', 'status' => 'active',
            'organization_unit_row_id' => $this->village->row_id,
        ]);
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan', 'loan_product_row_id' => $this->productId,
            'sequence_number' => 77, 'loan_number' => 'PK-NOKETUA', 'proposed_at' => '2026-01-01',
            'disbursed_at' => '2026-02-01', 'principal_amount' => 500000, 'interest_rate' => 1.5,
            'term_months' => 10, 'installment_method' => 'flat', 'status' => 'active',
        ]);
        LoanBorrower::query()->create(['loan_row_id' => $loan->row_id, 'group_row_id' => $group2->row_id, 'member_row_id' => null]);

        $data = app(LppReportService::class)->buildKelompok(2026, 3);
        $loans = collect($data['products'])->flatMap(fn ($p) => collect($p['villages'])->flatMap(fn ($v) => $v['loans']));

        self::assertSame('-', $loans->firstWhere('loan_number', 'PK-NOKETUA')['ketua']);
    }

    public function test_schedule_vs_actual_pengajuan_differs_from_pencairan_and_groups_products(): void
    {
        $this->seedLoan('2026-07-15', 1000000, 1500000, 1200000, 'disbursed', proposedAt: '2026-06-01');

        $report = app(LoanScheduleVsActualService::class)->build(2026, 7);

        self::assertArrayHasKey('products', $report);
        self::assertNotEmpty($report['products']);
        $loan = $report['products'][0]['villages'][0]['loans'][0];
        self::assertEqualsWithDelta(1500000.0, $loan['pengajuan'], 0.01);
        self::assertEqualsWithDelta(1200000.0, $loan['pencairan'], 0.01);
        self::assertSame('Ketua Siti', $loan['ketua']);
        self::assertSame('2026-06-01-PK', $loan['spk_no']);
        self::assertSame(2, $loan['pinjaman_anggota_count']);
        self::assertSame('flat', $loan['sistem_pokok']);
        self::assertSame(12, $loan['jangka']);

        $html = view('reports.pdf.loan_schedule_vs_actual', $report + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Ketua Siti', $html);
        self::assertStringContainsString('1,500,000', $html);
        self::assertStringContainsString('1,200,000', $html);

        $bytes = Pdf::loadView('reports.pdf.loan_schedule_vs_actual', $report + ['tanda_tangan' => ''])->setPaper('a4', 'landscape')->output();
        self::assertStringStartsWith('%PDF', $bytes);
    }

    public function test_tidak_layak_jenis_pinjaman_from_borrower_scope(): void
    {
        $this->seedUnfeasible(6000000, 'unfeasible');

        $data = app(UnfeasibleLoanReportService::class)->build(2026, 9);
        self::assertSame('Kelompok', $data['villages'][0]['loans'][0]['jenis_pinjaman']);

        // Produk 'member' scope -> jenis_pinjaman kosong.
        DB::connection('tenant')->table('loan_products')->where('row_id', $this->productId)->update(['borrower_scope' => 'member']);
        $data2 = app(UnfeasibleLoanReportService::class)->build(2026, 9);
        self::assertSame('', $data2['villages'][0]['loans'][0]['jenis_pinjaman']);

        $html = view('reports.pdf.lending.tidak_layak', $data + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Kelompok Kelompok Mawar', $html);
    }

    public function test_calk_profit_distribution_lists_village_rows_and_personalia(): void
    {
        $data = app(CalkService::class)->build(2026, 9);
        self::assertNotEmpty($data['profit_distribution']['villages'], 'Tabel Pembagian Laba harus berisi baris desa.');
        self::assertSame('Desa Sukamaju', $data['profit_distribution']['villages'][0]['name']);
        self::assertArrayHasKey('prior', $data['profit_distribution']['villages'][0]);
        self::assertArrayHasKey('current', $data['profit_distribution']['villages'][0]);

        self::assertNotEmpty($data['personalia'], 'Personalia CALK tidak boleh kosong.');
        self::assertSame('H. Sukarno', $data['personalia'][0]['nama']);

        $html = view('reports.pdf.calk', $data + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Desa Sukamaju', $html);
        self::assertStringContainsString('H. Sukarno', $html);

        $bytes = Pdf::loadView('reports.pdf.calk', $data + ['tanda_tangan' => ''])->setPaper('a4', 'portrait')->output();
        self::assertStringStartsWith('%PDF', $bytes);
    }

    public function test_cash_flow_opening_label_uses_period_from_date(): void
    {
        $data = app(CashFlowService::class)->build(2026, 7);
        self::assertSame('Saldo Awal per 01/07/2026', $data['opening_label']);

        $html = view('reports.pdf.cash_flow', $data + ['tanda_tangan' => ''])->render();
        self::assertStringContainsString('Saldo Awal per 01/07/2026', $html);

        $bytes = Pdf::loadView('reports.pdf.cash_flow', $data + ['tanda_tangan' => ''])->setPaper('a4', 'portrait')->output();
        self::assertStringStartsWith('%PDF', $bytes);
    }

    private function seedLoan(string $disbursed, float $principal, float $proposed, float $allocated, string $status, ?string $proposedAt = null): Loan
    {
        static $seq = 100;
        $seq++;
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan', 'loan_product_row_id' => $this->productId,
            'sequence_number' => $seq, 'loan_number' => ($proposedAt ?? $disbursed).'-PK',
            'proposed_at' => $proposedAt ?? '2026-01-01', 'disbursed_at' => $disbursed,
            'principal_amount' => $principal, 'interest_rate' => 1.5, 'term_months' => 12,
            'installment_method' => 'flat', 'status' => $status,
        ]);
        LoanBorrower::query()->create(['loan_row_id' => $loan->row_id, 'group_row_id' => $this->group->row_id, 'member_row_id' => null]);

        $members = Member::query()->orderBy('member_number')->take(2)->get();
        $count = max(1, $members->count());
        foreach ($members as $m) {
            LoanBeneficiary::query()->create([
                'loan_row_id' => $loan->row_id,
                'member_row_id' => $m->row_id,
                'allocated_amount' => round($allocated / $count, 2),
                'proposed_amount' => round($proposed / $count, 2),
            ]);
        }

        return $loan;
    }

    private function seedSimpleLoan(float $principal, string $disbursed, string $status, string $completedAt, ?string $proposedAt = null): Loan
    {
        static $seq2 = 200;
        $seq2++;
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan', 'loan_product_row_id' => $this->productId,
            'sequence_number' => $seq2, 'loan_number' => 'PK-'.$seq2,
            'proposed_at' => $proposedAt ?? '2026-01-01', 'disbursed_at' => $disbursed,
            'completed_at' => $completedAt, 'principal_amount' => $principal,
            'interest_rate' => 1.5, 'term_months' => 12, 'installment_method' => 'flat', 'status' => $status,
        ]);
        LoanBorrower::query()->create(['loan_row_id' => $loan->row_id, 'group_row_id' => $this->group->row_id, 'member_row_id' => null]);

        return $loan;
    }

    private function seedUnfeasible(float $amount, string $status): Loan
    {
        $loan = $this->seedSimpleLoan($amount, '2026-01-01', $status, '2026-09-10');
        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id, 'member_row_id' => $this->memberK->row_id, 'allocated_amount' => $amount,
        ]);

        return $loan;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Services\LoanService;
use App\Domain\Lending\Services\Reports\UnfeasibleLoanReportService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class UnfeasibleLoanReportTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private int $productId;

    private OrganizationUnit $village1;

    private OrganizationUnit $village2;

    private Group $group1;

    private Group $group2;

    private Member $member1;

    private Member $member2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Verifikasi',
            'email' => 'unfeasible-report@example.test',
            'username' => 'unfeasible_report_user',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);

        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $this->village1 = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Sukamaju',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->village2 = OrganizationUnit::query()->create([
            'id' => 2,
            'code' => 'V002',
            'name' => 'Desa Sukamakmur',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group1 = Group::query()->create([
            'code' => 'KLP-01',
            'name' => 'Kelompok Mawar',
            'status' => 'active',
            'address' => 'Dusun Krajan No. 1',
            'organization_unit_row_id' => $this->village1->row_id,
        ]);

        $this->group2 = Group::query()->create([
            'code' => 'KLP-02',
            'name' => 'Kelompok Melati',
            'status' => 'active',
            'address' => 'Dusun Tengah No. 2',
            'organization_unit_row_id' => $this->village2->row_id,
        ]);

        $p1 = Person::query()->create([
            'national_identity_number' => '3507010101900001',
            'full_name' => 'Aminah',
            'phone' => '081234567890',
            'gender' => 'P',
        ]);

        $p2 = Person::query()->create([
            'national_identity_number' => '3507010101900002',
            'full_name' => 'Budi',
            'phone' => '081298765432',
            'gender' => 'L',
        ]);

        $this->member1 = Member::query()->create([
            'person_row_id' => $p1->row_id,
            'organization_unit_row_id' => $this->village1->row_id,
            'member_number' => 'MBR-001',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $this->member2 = Member::query()->create([
            'person_row_id' => $p2->row_id,
            'organization_unit_row_id' => $this->village2->row_id,
            'member_number' => 'MBR-002',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_service_returns_accurate_data_for_unfeasible_loans(): void
    {
        $this->seedUnfeasibleLoan($this->group1, $this->member1, 6000000, 'unfeasible');
        $this->seedUnfeasibleLoan($this->group2, $this->member2, 4000000, 'tidak_layak');

        // Loan that is NOT unfeasible must be excluded.
        $this->seedLoan($this->group1, $this->member1, 9999999, 'active');

        $data = app(UnfeasibleLoanReportService::class)->build(2026, 9);

        self::assertSame(2026, $data['year']);
        self::assertSame(9, $data['month']);
        self::assertSame('September 2026', $data['period_label']);
        self::assertSame(2, $data['totals']['loans_count']);
        self::assertSame(2, $data['totals']['groups_count']);
        self::assertSame(2, $data['totals']['members_count']);
        self::assertEqualsWithDelta(10000000.0, $data['totals']['amount'], 0.01);

        self::assertCount(2, $data['villages']);
        self::assertSame('Desa Sukamaju', $data['villages'][0]['nama_desa']);
        self::assertSame('V001', $data['villages'][0]['kode_desa']);
        self::assertSame('Kelompok Mawar', $data['villages'][0]['loans'][0]['group_name']);
        self::assertEqualsWithDelta(6000000.0, $data['villages'][0]['subtotal']['amount'], 0.01);
    }

    public function test_service_filters_by_product_code(): void
    {
        $this->seedUnfeasibleLoan($this->group1, $this->member1, 6000000, 'unfeasible');

        $otherProductId = (int) DB::connection('tenant')->table('loan_products')->where('code', '!=', 'spp')->value('row_id');

        $data = app(UnfeasibleLoanReportService::class)->build(2026, 9, 'spp');
        self::assertSame(1, $data['totals']['loans_count']);

        $empty = app(UnfeasibleLoanReportService::class)->build(2026, 9, 'nonexistent-code');
        self::assertSame(0, $empty['totals']['loans_count']);
        self::assertNotNull($otherProductId);
    }

    public function test_tidak_layak_page_renders_inertia_component(): void
    {
        $this->seedUnfeasibleLoan($this->group1, $this->member1, 6000000, 'unfeasible');

        $this->actingAs($this->user)
            ->get('/lending/reports/tidak-layak?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Reports/TidakLayak')
                ->has('villages', 1)
                ->where('totals.groups_count', 1)
                ->where('totals.members_count', 1)
            );
    }

    public function test_tidak_layak_pdf_renders_and_streams_successfully(): void
    {
        $this->seedUnfeasibleLoan($this->group1, $this->member1, 6000000, 'unfeasible');

        $response = $this->actingAs($this->user)
            ->get('/lending/reports/tidak-layak/pdf?year=2026&month=9');

        $response->assertOk();
        self::assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    public function test_mark_unfeasible_changes_status_and_records_history(): void
    {
        $loan = $this->seedLoan($this->group1, $this->member1, 5000000, 'draft');

        app(LoanService::class)->markUnfeasible($loan, ['notes' => 'Usaha tidak memenuhi kelayakan.'], (int) $this->user->row_id);

        $loan->refresh();
        self::assertSame('unfeasible', $loan->status);

        $history = DB::connection('tenant')
            ->table('loan_status_histories')
            ->where('loan_row_id', $loan->row_id)
            ->where('to_status', 'unfeasible')
            ->first();

        self::assertNotNull($history);
        self::assertSame('draft', $history->from_status);
        self::assertSame('Usaha tidak memenuhi kelayakan.', $history->notes);
    }

    public function test_mark_unfeasible_rejects_invalid_status(): void
    {
        $loan = $this->seedLoan($this->group1, $this->member1, 5000000, 'active');

        $this->expectException(\RuntimeException::class);
        app(LoanService::class)->markUnfeasible($loan, [], (int) $this->user->row_id);
    }

    public function test_dashboard_pipeline_modal_loads_unfeasible_loans(): void
    {
        $this->seedUnfeasibleLoan($this->group1, $this->member1, 6000000, 'unfeasible');

        $this->actingAs($this->user)
            ->get('/dashboard?pipeline=tidak_layak')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pipeline_modal_key', 'tidak_layak')
                ->where('pipeline_modal.label', 'Tidak Layak')
                ->where('pipeline_modal.total', 1)
                ->has('pipeline_modal.rows', 1)
            );
    }

    private function seedUnfeasibleLoan(Group $group, Member $member, float $amount, string $status): Loan
    {
        return $this->seedLoan($group, $member, $amount, $status, notes: 'Anggota dinyatakan tidak layak menerima pinjaman.');
    }

    private function seedLoan(Group $group, Member $member, float $amount, string $status, ?string $notes = null): Loan
    {
        static $seq = 0;
        $seq++;

        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => $seq,
            'loan_number' => sprintf('%03d/SPK/001/09/2026', $seq),
            'proposed_at' => '2026-08-15',
            'principal_amount' => $amount,
            'interest_rate' => 1.5,
            'term_months' => 10,
            'installment_method' => 'flat',
            'status' => $status,
            'verification_notes' => $notes,
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $group->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $member->row_id,
            'allocated_amount' => $amount,
        ]);

        if (in_array($status, ['unfeasible', 'tidak_layak'], true)) {
            $loan->statusHistories()->create([
                'from_status' => 'draft',
                'to_status' => 'unfeasible',
                'notes' => 'Pinjaman ditandai tidak layak.',
                'changed_by_user_id' => $this->user->row_id,
                'changed_at' => '2026-09-10 09:00:00',
            ]);
        }

        return $loan;
    }
}

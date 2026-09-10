<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Lending\Services\Reports\LoanBillingNoticeReportService;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanBillingNoticeReportTest extends TestCase
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
            'name' => 'Petugas Tagihan',
            'email' => 'billing-report@example.test',
            'username' => 'billing_report_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();
        $this->productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 9,
            'starts_at' => '2026-09-01',
            'ends_at' => '2026-09-30',
            'status' => 'open',
        ]);

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
            'organization_unit_row_id' => $this->village1->row_id,
        ]);

        $this->group2 = Group::query()->create([
            'code' => 'KLP-02',
            'name' => 'Kelompok Melati',
            'status' => 'active',
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

    public function test_billing_notice_service_builds_correct_payload_and_filters(): void
    {
        $this->seedLoansAndInstallments();

        $service = app(LoanBillingNoticeReportService::class);

        // All due in September 2026
        $all = $service->build(2026, 9, villageRowId: null, groupRowId: null, onlyDue: true);
        self::assertSame(2, $all['totals']['groups_count']);
        self::assertSame(2, $all['totals']['members_count']);
        self::assertEqualsWithDelta(1100000.0, $all['totals']['total'], 0.01);

        // Filter village1
        $village1Data = $service->build(2026, 9, villageRowId: (int) $this->village1->row_id, groupRowId: null, onlyDue: true);
        self::assertSame(1, $village1Data['totals']['groups_count']);
        self::assertSame('Kelompok Mawar', $village1Data['groups'][0]['group_name']);

        // Filter group2
        $group2Data = $service->build(2026, 9, villageRowId: null, groupRowId: (int) $this->group2->row_id, onlyDue: true);
        self::assertSame(1, $group2Data['totals']['groups_count']);
        self::assertSame('Kelompok Melati', $group2Data['groups'][0]['group_name']);
    }

    public function test_billing_notice_page_renders_inertia_component(): void
    {
        $this->seedLoansAndInstallments();

        $this->actingAs($this->user)
            ->get('/lending/reports/billing-notice?year=2026&month=9')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Lending/Reports/BillingNotice')
                ->has('groups', 2)
                ->where('totals.members_count', 2)
            );
    }

    public function test_billing_notice_pdf_renders_and_streams_successfully(): void
    {
        $this->seedLoansAndInstallments();

        $response = $this->actingAs($this->user)
            ->get('/lending/reports/billing-notice/pdf?year=2026&month=9');

        $response->assertOk();
        self::assertStringContainsString('application/pdf', (string) $response->headers->get('Content-Type'));
    }

    private function seedLoansAndInstallments(): void
    {
        // Loan 1 (Mawar)
        $loan1 = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 1,
            'loan_number' => '001/SPK/001/09/2026',
            'proposed_at' => '2026-08-01',
            'disbursed_at' => '2026-09-01',
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'term_months' => 10,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan1->row_id,
            'group_row_id' => $this->group1->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan1->row_id,
            'member_row_id' => $this->member1->row_id,
            'allocated_amount' => 5000000,
        ]);

        // Installment due September
        LoanInstallment::query()->create([
            'loan_row_id' => $loan1->row_id,
            'installment_number' => 1,
            'due_date' => '2026-09-15',
            'principal_due' => 500000,
            'principal_paid' => 0,
            'interest_due' => 50000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);

        // Loan 2 (Melati)
        $loan2 = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => 2,
            'loan_number' => '002/SPK/001/09/2026',
            'proposed_at' => '2026-08-01',
            'disbursed_at' => '2026-09-01',
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'term_months' => 10,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan2->row_id,
            'group_row_id' => $this->group2->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan2->row_id,
            'member_row_id' => $this->member2->row_id,
            'allocated_amount' => 5000000,
        ]);

        LoanInstallment::query()->create([
            'loan_row_id' => $loan2->row_id,
            'installment_number' => 1,
            'due_date' => '2026-09-20',
            'principal_due' => 500000,
            'principal_paid' => 0,
            'interest_due' => 50000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);
    }
}

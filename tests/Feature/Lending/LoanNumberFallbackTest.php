<?php

declare(strict_types=1);

namespace Tests\Feature\Lending;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Services\LoanService;
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
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LoanNumberFallbackTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private int $productId;

    private Group $group;

    private Member $member;

    private Account $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Petugas Pinjaman',
            'email' => 'loan-spk@example.test',
            'username' => 'loan_spk_user',
            'password' => 'password',
            'status' => 'active',
        ]);

        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Sukamaju',
            'type' => 'village',
            'is_active' => true,
        ]);

        $this->group = Group::query()->create([
            'code' => 'KLP-01',
            'name' => 'Kelompok Melati',
            'status' => 'active',
            'organization_unit_row_id' => $village->row_id,
        ]);

        $person = Person::query()->create([
            'national_identity_number' => '3507010101900001',
            'full_name' => 'Siti Aminah',
            'gender' => 'P',
        ]);

        $this->member = Member::query()->create([
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-001',
            'registered_at' => '2026-01-01',
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

        $this->cashAccount = Account::query()->where('code', '1.1.01.01')->firstOrFail();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_fallback_generates_spk_number_when_empty(): void
    {
        $loan = $this->createApprovedLoan();

        $disbursed = app(LoanService::class)->disburse($loan, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'disbursement_notes' => 'Pencairan lancar',
            'loan_number' => null,
        ], (int) $this->user->row_id);

        $districtCode = trim((string) ($this->testTenant->district_code ?? '')) ?: '000';
        self::assertSame("001/SPK/{$districtCode}/09/2026", $disbursed->loan_number);
    }

    public function test_two_consecutive_disbursements_get_unique_sequential_numbers(): void
    {
        $loan1 = $this->createApprovedLoan();
        $loan2 = $this->createApprovedLoan();

        $disbursed1 = app(LoanService::class)->disburse($loan1, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'loan_number' => null,
        ], (int) $this->user->row_id);

        $disbursed2 = app(LoanService::class)->disburse($loan2, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'loan_number' => null,
        ], (int) $this->user->row_id);

        $districtCode = trim((string) ($this->testTenant->district_code ?? '')) ?: '000';
        self::assertSame("001/SPK/{$districtCode}/09/2026", $disbursed1->loan_number);
        self::assertSame("002/SPK/{$districtCode}/09/2026", $disbursed2->loan_number);
        self::assertNotSame($disbursed1->loan_number, $disbursed2->loan_number);
    }

    public function test_manual_loan_number_is_respected_and_not_overwritten(): void
    {
        $loan = $this->createApprovedLoan();

        $disbursed = app(LoanService::class)->disburse($loan, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'loan_number' => 'CUSTOM/SPK/MANUAL/007',
        ], (int) $this->user->row_id);

        self::assertSame('CUSTOM/SPK/MANUAL/007', $disbursed->loan_number);
    }

    public function test_duplicate_manual_loan_number_is_rejected_with_validation_exception(): void
    {
        $loan1 = $this->createApprovedLoan();
        $loan2 = $this->createApprovedLoan();

        app(LoanService::class)->disburse($loan1, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'loan_number' => 'DUPLICATE/SPK/001',
        ], (int) $this->user->row_id);

        $this->expectException(ValidationException::class);

        app(LoanService::class)->disburse($loan2, [
            'disbursed_at' => '2026-09-10',
            'disbursement_account_row_id' => $this->cashAccount->row_id,
            'loan_number' => 'DUPLICATE/SPK/001',
        ], (int) $this->user->row_id);
    }

    private function createApprovedLoan(): Loan
    {
        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $this->productId,
            'sequence_number' => random_int(100, 99999),
            'proposed_at' => '2026-09-01',
            'approved_at' => '2026-09-05',
            'funded_at' => '2026-09-10',
            'principal_amount' => 5000000,
            'interest_rate' => 1.5,
            'service_rate_total' => 18.0,
            'term_months' => 12,
            'principal_frequency' => 'monthly',
            'interest_frequency' => 'monthly',
            'principal_grace_months' => 0,
            'interest_grace_months' => 0,
            'installment_method' => 'flat',
            'status' => 'waiting',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $this->group->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $this->member->row_id,
            'allocated_amount' => 5000000,
        ]);

        return $loan;
    }
}

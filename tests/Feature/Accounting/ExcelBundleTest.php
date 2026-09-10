<?php

declare(strict_types=1);

namespace Tests\Feature\Accounting;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Services\JournalPostingService;
use App\Domain\Lending\Models\Loan;
use App\Domain\Lending\Models\LoanBeneficiary;
use App\Domain\Lending\Models\LoanBorrower;
use App\Domain\Lending\Models\LoanInstallment;
use App\Domain\Membership\Models\Group;
use App\Domain\Membership\Models\Member;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Membership\Models\Person;
use App\Models\Tenant\OrganizationUnit;
use App\Models\User;
use App\Tenancy\Middleware\ResolveTenant;
use App\Tenancy\Services\DefaultChartOfAccountsProvisioner;
use App\Tenancy\Services\TenantLoanProductProvisioner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class ExcelBundleTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $user;

    private User $restrictedUser;

    private Account $cash;

    private Account $equity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        $this->user = $this->createUser('auditor@example.test');
        $this->restrictedUser = $this->createUser('restricted-auditor@example.test');
        app(PermissionChecker::class)->assignRole($this->restrictedUser, 'viewer');

        app(DefaultChartOfAccountsProvisioner::class)->ensureDefaults();
        app(TenantLoanProductProvisioner::class)->ensureDefaults();

        $this->seedAccountsAndTransactions();
        $this->seedLoans();

        OrganizationProfile::query()->create([
            'id' => 1,
            'legal_name' => 'BUMDesma Mandiri LKD',
            'short_name' => 'Mandiri LKD',
            'district_name' => 'Singosari',
            'regency_name' => 'Malang',
        ]);
    }

    protected function tearDown(): void
    {
        $this->deleteReportBundles();
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_authorized_user_can_download_excel_auditor_bundle(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/accounting/reports/bundle/xlsx?year=2026&month=12');

        $response->assertOk();
        self::assertStringContainsString('spreadsheetml.sheet', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('bundle-auditor-mandiri-lkd-2026-12.xlsx', (string) $response->headers->get('Content-Disposition'));

        $filePath = $response->getFile()->getPathname();
        self::assertFileExists($filePath);

        $reader = new Reader;
        $reader->open($filePath);

        $sheetNames = [];
        $neracaHasRows = false;
        $neracaRowValues = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getName();
            if ($sheet->getName() === 'Neraca') {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $row->toArray();
                    $neracaRowValues[] = $cells;
                    if (! empty($cells[0]) && $cells[0] !== 'Kode Akun') {
                        $neracaHasRows = true;
                    }
                }
            }
        }

        $reader->close();

        self::assertGreaterThanOrEqual(7, count($sheetNames));
        self::assertContains('Ringkasan', $sheetNames);
        self::assertContains('Neraca', $sheetNames);
        self::assertContains('Laba Rugi', $sheetNames);
        self::assertContains('Arus Kas', $sheetNames);
        self::assertContains('Neraca Saldo', $sheetNames);
        self::assertContains('Buku Besar', $sheetNames);
        self::assertContains('Piutang', $sheetNames);

        self::assertTrue($neracaHasRows, 'Sheet Neraca harus berisi baris akun.');
    }

    public function test_viewer_role_with_report_permission_can_download_excel_bundle(): void
    {
        $this->actingAs($this->restrictedUser)
            ->get('/accounting/reports/bundle/xlsx?year=2026&month=12')
            ->assertOk();
    }

    private function createUser(string $email): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Auditor User',
            'email' => $email,
            'username' => Str::before($email, '@'),
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    private function seedAccountsAndTransactions(): void
    {
        FiscalPeriod::query()->create([
            'fiscal_year' => 2026,
            'fiscal_month' => 12,
            'starts_at' => '2026-12-01',
            'ends_at' => '2026-12-31',
            'status' => 'open',
        ]);

        $this->cash = Account::query()->where('code', '1.1.01.01')->firstOrFail();
        $this->equity = Account::query()->where('code', '3.1.01.01')->firstOrFail();

        $entry = JournalEntry::query()->create([
            'transaction_date' => '2026-12-05',
            'sequence_number' => 1,
            'description' => 'Setor modal tambahan',
            'status' => 'draft',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 1,
            'account_row_id' => $this->cash->row_id,
            'debit' => '5000000.00',
            'credit' => '0.00',
        ]);
        JournalLine::query()->create([
            'journal_entry_row_id' => $entry->row_id,
            'line_number' => 2,
            'account_row_id' => $this->equity->row_id,
            'debit' => '0.00',
            'credit' => '5000000.00',
        ]);
        app(JournalPostingService::class)->post($entry, (int) $this->user->row_id);
    }

    private function seedLoans(): void
    {
        $village = OrganizationUnit::query()->create([
            'id' => 1,
            'code' => 'V001',
            'name' => 'Desa Sukamaju',
            'type' => 'village',
            'is_active' => true,
        ]);

        $group = Group::query()->create([
            'code' => 'KLP-AUDIT',
            'name' => 'Kelompok Audit',
            'status' => 'active',
            'organization_unit_row_id' => $village->row_id,
        ]);

        $person = Person::query()->create([
            'national_identity_number' => '3507010101900009',
            'full_name' => 'Ibu Rahayu',
            'gender' => 'P',
        ]);

        $member = Member::query()->create([
            'person_row_id' => $person->row_id,
            'organization_unit_row_id' => $village->row_id,
            'member_number' => 'MBR-AUDIT',
            'registered_at' => '2026-01-01',
            'status' => 'active',
        ]);

        $productId = (int) DB::connection('tenant')->table('loan_products')->where('code', 'spp')->value('row_id');

        $loan = Loan::query()->create([
            'legacy_source' => 'group_loan',
            'loan_product_row_id' => $productId,
            'sequence_number' => 1,
            'loan_number' => '001/SPK/000/12/2026',
            'proposed_at' => '2026-11-01',
            'disbursed_at' => '2026-12-01',
            'principal_amount' => 10000000,
            'interest_rate' => 1.5,
            'term_months' => 12,
            'installment_method' => 'flat',
            'status' => 'active',
        ]);

        LoanBorrower::query()->create([
            'loan_row_id' => $loan->row_id,
            'group_row_id' => $group->row_id,
            'member_row_id' => null,
        ]);

        LoanBeneficiary::query()->create([
            'loan_row_id' => $loan->row_id,
            'member_row_id' => $member->row_id,
            'allocated_amount' => 10000000,
        ]);

        LoanInstallment::query()->create([
            'loan_row_id' => $loan->row_id,
            'installment_number' => 1,
            'due_date' => '2026-12-20',
            'principal_due' => 800000,
            'principal_paid' => 0,
            'interest_due' => 120000,
            'interest_paid' => 0,
            'penalty_due' => 0,
            'penalty_paid' => 0,
            'status' => 'pending',
        ]);
    }

    private function deleteReportBundles(): void
    {
        $directory = storage_path('app/report-bundles');
        if (! is_dir($directory)) {
            return;
        }

        foreach (glob($directory.'/*') ?: [] as $path) {
            @unlink($path);
        }
    }
}

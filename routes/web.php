<?php

declare(strict_types=1);

use App\Http\Controllers\Assistant\EmbedTokenController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Accounting\TaxEstimateController;
use App\Http\Controllers\Budgeting\BudgetController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Lending\LoanController;
use App\Http\Controllers\Lending\LoanReportController;
use App\Http\Controllers\MasterData\GroupController;
use App\Http\Controllers\MasterData\MemberController;
use App\Http\Controllers\MasterData\OtherInstitutionController;
use App\Http\Controllers\MasterData\VillageController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController as AdminInvoicePaymentController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\TenantUserController as AdminTenantUserController;
use App\Http\Controllers\Billing\InvoiceController as TenantInvoiceController;
use App\Http\Controllers\RegionalCodeController;
use App\Http\Controllers\Webhooks\TripayWebhookController;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home', [
    'name' => config('app.name'),
    'status' => 'ok',
]));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::post('/tripay/callback', TripayWebhookController::class)->name('tripay.callback');

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    Route::get('/tenants', [AdminTenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/create', [AdminTenantController::class, 'create'])->name('tenants.create');
    Route::post('/tenants', [AdminTenantController::class, 'store'])->name('tenants.store');
    Route::get('/tenants/{tenant}', [AdminTenantController::class, 'show'])->name('tenants.show');
    Route::get('/tenants/{tenant}/edit', [AdminTenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/tenants/{tenant}', [AdminTenantController::class, 'update'])->name('tenants.update');
    Route::post('/tenants/{tenant}/suspend', [AdminTenantController::class, 'suspend'])->name('tenants.suspend');
    Route::post('/tenants/{tenant}/activate', [AdminTenantController::class, 'activate'])->name('tenants.activate');
    Route::post('/tenants/{tenant}/subscription', [AdminTenantController::class, 'assignSubscription'])->name('tenants.subscription');

    Route::get('/tenants/{tenant}/users', [AdminTenantUserController::class, 'index'])->name('tenants.users.index');
    Route::get('/tenants/{tenant}/users/create', [AdminTenantUserController::class, 'create'])->name('tenants.users.create');
    Route::post('/tenants/{tenant}/users', [AdminTenantUserController::class, 'store'])->name('tenants.users.store');
    Route::get('/tenants/{tenant}/users/{user}/edit', [AdminTenantUserController::class, 'edit'])->name('tenants.users.edit');
    Route::put('/tenants/{tenant}/users/{user}', [AdminTenantUserController::class, 'update'])->name('tenants.users.update');
    Route::post('/tenants/{tenant}/users/{user}/reset-password', [AdminTenantUserController::class, 'resetPassword'])->name('tenants.users.reset-password');

    Route::get('/plans', [AdminPlanController::class, 'index'])->name('plans.index');
    Route::get('/plans/create', [AdminPlanController::class, 'create'])->name('plans.create');
    Route::post('/plans', [AdminPlanController::class, 'store'])->name('plans.store');
    Route::get('/plans/{plan}/edit', [AdminPlanController::class, 'edit'])->name('plans.edit');
    Route::put('/plans/{plan}', [AdminPlanController::class, 'update'])->name('plans.update');

    Route::get('/invoices', [AdminInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/create', [AdminInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/invoices', [AdminInvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/invoices/{invoice}', [AdminInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/void', [AdminInvoiceController::class, 'void'])->name('invoices.void');
    Route::post('/invoices/{invoice}/payments/manual', [AdminInvoicePaymentController::class, 'storeManual'])->name('invoices.payments.manual');
    Route::post('/invoices/{invoice}/payments/tripay', [AdminInvoicePaymentController::class, 'storeTripay'])->name('invoices.payments.tripay');
    Route::post('/subscriptions/{subscription}/invoices', [AdminInvoiceController::class, 'generateFromSubscription'])->name('subscriptions.invoices.store');

    Route::get('/regional/provinces', [RegionalCodeController::class, 'provinces'])->name('regional.provinces');
    Route::get('/regional/regencies/{province}', [RegionalCodeController::class, 'regencies'])->name('regional.regencies');
    Route::get('/regional/districts/{regency}', [RegionalCodeController::class, 'districts'])->name('regional.districts');
});

Route::redirect('/superadmin/tenants', '/admin/tenants');
Route::redirect('/superadmin/tenants/create', '/admin/tenants/create');
Route::redirect('/superadmin', '/admin');

Route::middleware(['auth', 'tenant'])->group(function (): void {
    Route::get('/api/assistant/embed-token', EmbedTokenController::class)
        ->name('assistant.embed-token');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('/profile/account', [ProfileController::class, 'updateAccount'])->name('profile.account.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');

    Route::get('/master-data/members', [MemberController::class, 'index'])->name('master-data.members.index');
    Route::get('/master-data/members/create', [MemberController::class, 'create'])->name('master-data.members.create');
    Route::get('/master-data/members/lookup', [MemberController::class, 'lookup'])->name('master-data.members.lookup');
    Route::get('/master-data/members/export', [MemberController::class, 'export'])->name('master-data.members.export');
    Route::post('/master-data/members/import', [MemberController::class, 'import'])->name('master-data.members.import');
    Route::post('/master-data/members', [MemberController::class, 'store'])->name('master-data.members.store');
    Route::get('/master-data/members/{member}', [MemberController::class, 'show'])->name('master-data.members.show');
    Route::get('/master-data/members/{member}/edit', [MemberController::class, 'edit'])->name('master-data.members.edit');
    Route::put('/master-data/members/{member}', [MemberController::class, 'update'])->name('master-data.members.update');
    Route::delete('/master-data/members/{member}', [MemberController::class, 'destroy'])->name('master-data.members.destroy');

    Route::get('/master-data/groups', [GroupController::class, 'index'])->name('master-data.groups.index');
    Route::get('/master-data/groups/create', [GroupController::class, 'create'])->name('master-data.groups.create');
    Route::get('/master-data/groups/member-options', [GroupController::class, 'memberOptions'])->name('master-data.groups.member-options');
    Route::get('/master-data/groups/export', [GroupController::class, 'export'])->name('master-data.groups.export');
    Route::post('/master-data/groups/import', [GroupController::class, 'import'])->name('master-data.groups.import');
    Route::post('/master-data/groups/members', [GroupController::class, 'storeMember'])->name('master-data.groups.members.store');
    Route::post('/master-data/groups', [GroupController::class, 'store'])->name('master-data.groups.store');
    Route::get('/master-data/groups/{group}', [GroupController::class, 'show'])->name('master-data.groups.show');
    Route::get('/master-data/groups/{group}/edit', [GroupController::class, 'edit'])->name('master-data.groups.edit');
    Route::put('/master-data/groups/{group}', [GroupController::class, 'update'])->name('master-data.groups.update');
    Route::delete('/master-data/groups/{group}', [GroupController::class, 'destroy'])->name('master-data.groups.destroy');

    Route::get('/master-data/villages', [VillageController::class, 'index'])->name('master-data.villages.index');
    Route::get('/master-data/villages/{village}/edit', [VillageController::class, 'edit'])->name('master-data.villages.edit');
    Route::put('/master-data/villages/{village}', [VillageController::class, 'update'])->name('master-data.villages.update');

    Route::get('/master-data/institutions', [OtherInstitutionController::class, 'index'])->name('master-data.institutions.index');
    Route::get('/master-data/institutions/create', [OtherInstitutionController::class, 'create'])->name('master-data.institutions.create');
    Route::get('/master-data/institutions/export', [OtherInstitutionController::class, 'export'])->name('master-data.institutions.export');
    Route::post('/master-data/institutions/import', [OtherInstitutionController::class, 'import'])->name('master-data.institutions.import');
    Route::post('/master-data/institutions', [OtherInstitutionController::class, 'store'])->name('master-data.institutions.store');
    Route::get('/master-data/institutions/{institution}', [OtherInstitutionController::class, 'show'])->name('master-data.institutions.show');
    Route::get('/master-data/institutions/{institution}/edit', [OtherInstitutionController::class, 'edit'])->name('master-data.institutions.edit');
    Route::put('/master-data/institutions/{institution}', [OtherInstitutionController::class, 'update'])->name('master-data.institutions.update');

    Route::get('/lending/loans', [LoanController::class, 'index'])->name('lending.loans.index');
    Route::get('/lending/loans/create', [LoanController::class, 'create'])->name('lending.loans.create');
    Route::get('/lending/loans/beneficiary-options', [LoanController::class, 'beneficiaryOptions'])->name('lending.loans.beneficiary-options');
    Route::post('/lending/loans', [LoanController::class, 'store'])->name('lending.loans.store');
    Route::get('/lending/loans/{loan}', [LoanController::class, 'show'])->name('lending.loans.show');
    Route::get('/lending/loans/{loan}/card', [LoanController::class, 'card'])->name('lending.loans.card');
    Route::put('/lending/loans/{loan}', [LoanController::class, 'update'])->name('lending.loans.update');
    Route::delete('/lending/loans/{loan}/beneficiaries/{member}', [LoanController::class, 'removeBeneficiary'])->name('lending.loans.beneficiaries.destroy');
    Route::patch('/lending/loans/{loan}/verify', [LoanController::class, 'verify'])->name('lending.loans.verify');
    Route::patch('/lending/loans/{loan}/approve', [LoanController::class, 'approve'])->name('lending.loans.approve');
    Route::patch('/lending/loans/{loan}/disburse', [LoanController::class, 'disburse'])->name('lending.loans.disburse');
    Route::patch('/lending/loans/{loan}/revert', [LoanController::class, 'revert'])->name('lending.loans.revert');
    Route::patch('/lending/loans/{loan}/committee', [LoanController::class, 'setCommittee'])->name('lending.loans.committee');
    Route::post('/lending/loans/{loan}/reschedule', [LoanController::class, 'reschedule'])->name('lending.loans.reschedule');
    Route::post('/lending/loans/{loan}/write-off', [LoanController::class, 'writeOff'])->name('lending.loans.write-off');

    Route::prefix('lending/reports')->name('lending.reports.')->group(function (): void {
        Route::get('/portfolio', [LoanReportController::class, 'portfolio'])->name('portfolio');
        Route::get('/portfolio/pdf', [LoanReportController::class, 'portfolioPdf'])->name('portfolio.pdf');
        Route::get('/schedule-vs-actual', [LoanReportController::class, 'scheduleVsActual'])->name('schedule-vs-actual');
        Route::get('/schedule-vs-actual/pdf', [LoanReportController::class, 'scheduleVsActualPdf'])->name('schedule-vs-actual.pdf');
    });

    Route::prefix('accounting')->name('accounting.')->group(function (): void {
        Route::get('/journals', [\App\Http\Controllers\Accounting\JournalBrowseController::class, 'index'])->name('journals.index');
        Route::post('/journals/{entry}/reverse', [\App\Http\Controllers\Accounting\JournalBrowseController::class, 'reverse'])->name('journals.reverse');
        Route::get('/journal-entries/create', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'create'])->name('journal-entries.create');
        Route::post('/journal-entries', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'store'])->name('journal-entries.store');
        Route::get('/journal-entries/installment', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'installment'])->name('journal-entries.installment');
        Route::post('/journal-entries/installment', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'storeInstallment'])->name('journal-entries.installment.store');
        Route::get('/journal-entries/{entry}/installment-receipt', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'installmentReceipt'])->name('journal-entries.installment.receipt');
        Route::get('/loans/{loan}/group-detail', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'loanGroupDetail'])->name('loans.group-detail');
        Route::get('/loans/{loan}/installment-history', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'loanInstallmentHistory'])->name('loans.installment-history');
        Route::get('/loans/{loan}/member-options', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'groupMemberOptions'])->name('loans.member-options');
        Route::get('/period-close', [\App\Http\Controllers\Accounting\PeriodCloseController::class, 'index'])->name('period-close.index');
        Route::post('/period-close/{year}/{month}/close', [\App\Http\Controllers\Accounting\PeriodCloseController::class, 'closeMonth'])
            ->whereNumber(['year', 'month'])
            ->name('period-close.month.close');
        Route::post('/period-close/{year}/{month}/reopen', [\App\Http\Controllers\Accounting\PeriodCloseController::class, 'reopenMonth'])
            ->whereNumber(['year', 'month'])
            ->name('period-close.month.reopen');
        Route::post('/period-close/year', [\App\Http\Controllers\Accounting\PeriodCloseController::class, 'closeYear'])->name('period-close.year');
    });

    Route::prefix('budgeting')->name('budgeting.')->group(function (): void {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::put('/{year}/{month}', [BudgetController::class, 'save'])->whereNumber(['year', 'month'])->name('save');
        Route::post('/{year}/{month}/copy-previous', [BudgetController::class, 'copyPrevious'])->whereNumber(['year', 'month'])->name('copy-previous');
        Route::post('/{year}/approve', [BudgetController::class, 'approve'])->whereNumber('year')->name('approve');
        Route::post('/{year}/reopen', [BudgetController::class, 'reopen'])->whereNumber('year')->name('reopen');
    });

    Route::get('/accounting/tax-estimate', [TaxEstimateController::class, 'index'])->name('accounting.tax-estimate');

    Route::prefix('accounting/reports')->name('accounting.reports.')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Accounting\ReportController::class, 'index'])->name('index');
        Route::get('/journals', [\App\Http\Controllers\Accounting\ReportController::class, 'journals'])->name('journals');
        Route::get('/journals/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'journalsPdf'])->name('journals.pdf');
        Route::get('/trial-balance', [\App\Http\Controllers\Accounting\ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('/trial-balance/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'trialBalancePdf'])->name('trial-balance.pdf');
        Route::get('/balance-sheet', [\App\Http\Controllers\Accounting\ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('/balance-sheet/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'balanceSheetPdf'])->name('balance-sheet.pdf');
        Route::get('/income-statement', [\App\Http\Controllers\Accounting\ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('/income-statement/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'incomeStatementPdf'])->name('income-statement.pdf');
        Route::get('/cash-flow', [\App\Http\Controllers\Accounting\ReportController::class, 'cashFlow'])->name('cash-flow');
        Route::get('/cash-flow/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'cashFlowPdf'])->name('cash-flow.pdf');
        Route::get('/equity-change', [\App\Http\Controllers\Accounting\ReportController::class, 'equityChange'])->name('equity-change');
        Route::get('/equity-change/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'equityChangePdf'])->name('equity-change.pdf');
        Route::get('/calk', [\App\Http\Controllers\Accounting\ReportController::class, 'calk'])->name('calk');
        Route::get('/calk/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'calkPdf'])->name('calk.pdf');
        Route::put('/calk/notes', [\App\Http\Controllers\Accounting\ReportController::class, 'saveCalkNotes'])->name('calk.notes');
        Route::get('/general-ledger', [\App\Http\Controllers\Accounting\ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('/general-ledger/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'generalLedgerPdf'])->name('general-ledger.pdf');
    });

    Route::prefix('notifications')->name('notifications.')->group(function (): void {
        Route::get('/billing', [\App\Http\Controllers\Notifications\BillingNoticeController::class, 'index'])->name('billing');
        Route::post('/billing/send', [\App\Http\Controllers\Notifications\BillingNoticeController::class, 'send'])->name('billing.send');
    });

    Route::prefix('billing')->name('billing.')->group(function (): void {
        Route::get('/invoices', [TenantInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('invoices.show');
        Route::post('/invoices/{invoice}/pay', [TenantInvoiceController::class, 'pay'])->name('invoices.pay');
    });

    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('/', [\App\Http\Controllers\Settings\SettingsController::class, 'index'])->name('index');
        Route::put('/identity', [\App\Http\Controllers\Settings\SettingsController::class, 'updateIdentity'])->name('identity.update');
        Route::put('/lending-system', [\App\Http\Controllers\Settings\SettingsController::class, 'updateLendingSystem'])->name('lending-system.update');
        Route::post('/logo', [\App\Http\Controllers\Settings\SettingsController::class, 'updateLogo'])->name('logo.update');
        Route::delete('/logo', [\App\Http\Controllers\Settings\SettingsController::class, 'destroyLogo'])->name('logo.destroy');
        Route::put('/whatsapp', [\App\Http\Controllers\Settings\SettingsController::class, 'updateWhatsapp'])->name('whatsapp.update');
        Route::post('/whatsapp/test', [\App\Http\Controllers\Settings\SettingsController::class, 'testWhatsapp'])->name('whatsapp.test');
        Route::post('/whatsapp/pair', [\App\Http\Controllers\Settings\SettingsController::class, 'pairWhatsapp'])->name('whatsapp.pair');
        Route::put('/signatures', [\App\Http\Controllers\Settings\SettingsController::class, 'updateSignatures'])->name('signatures.update');
    });
});

Route::middleware(['auth', 'tenant'])
    ->prefix('t/{tenant}')
    ->get('/health', fn () => response()->json([
        'tenant_id' => app(TenantContext::class)->id(),
        'tenant_code' => app(TenantContext::class)->tenant()->code,
        'shard' => app(TenantContext::class)->shard()->code,
    ]));

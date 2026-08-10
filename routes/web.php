<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\IntegrationController as AdminIntegrationController;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoiceController;
use App\Http\Controllers\Admin\InvoicePaymentController as AdminInvoicePaymentController;
use App\Http\Controllers\Admin\MigrationController as AdminMigrationController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\TenantController as AdminTenantController;
use App\Http\Controllers\Admin\TenantUserController as AdminTenantUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Billing\InvoiceController as TenantInvoiceController;
use App\Http\Controllers\Regency\RegencyDashboardController;
use App\Http\Controllers\Regency\RegencyReportController;
use App\Http\Controllers\RegionalCodeController;
use App\Http\Controllers\SearchController;
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
    Route::post('/tenants/{tenant}/repair', [AdminTenantController::class, 'repair'])->name('tenants.repair');
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

    Route::get('/migrations', [AdminMigrationController::class, 'index'])->name('migrations.index');
    Route::post('/migrations', [AdminMigrationController::class, 'store'])->name('migrations.store');
    Route::get('/migrations/{run}', [AdminMigrationController::class, 'show'])->name('migrations.show');

    Route::get('/regional/provinces', [RegionalCodeController::class, 'provinces'])->name('regional.provinces');
    Route::get('/regional/regencies/{province}', [RegionalCodeController::class, 'regencies'])->name('regional.regencies');
    Route::get('/integrations', [AdminIntegrationController::class, 'index'])->name('integrations.index');
    Route::post('/integrations/tripay', [AdminIntegrationController::class, 'updateTripay'])->name('integrations.tripay');
    Route::post('/integrations/tripay/test', [AdminIntegrationController::class, 'testTripay'])->name('integrations.tripay.test');
    Route::post('/integrations/whatsapp', [AdminIntegrationController::class, 'updateWhatsapp'])->name('integrations.whatsapp');
    Route::post('/integrations/whatsapp/pair', [AdminIntegrationController::class, 'pairWhatsapp'])->name('integrations.whatsapp.pair');
    Route::post('/integrations/whatsapp/test', [AdminIntegrationController::class, 'testWhatsapp'])->name('integrations.whatsapp.test');
    Route::post('/integrations/ai', [AdminIntegrationController::class, 'updateAi'])->name('integrations.ai');
    Route::post('/integrations/ai/sync-sources', [AdminIntegrationController::class, 'syncKnowledgeSources'])->name('integrations.ai.sync-sources');
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('/dashboard', fn () => TenantContext::getTenant()
        ? app(\App\Http\Controllers\DashboardController::class)($this->app->request)
        : redirect()->route('admin.dashboard')
    )->name('dashboard');

    Route::prefix('regency')->name('regency.')->middleware(['regency.user'])->group(function (): void {
        Route::get('/dashboard', [RegencyDashboardController::class, 'index'])->name('dashboard');
        Route::get('/reports/balance-sheet', [RegencyReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
        Route::get('/reports/income-statement', [RegencyReportController::class, 'incomeStatement'])->name('reports.income-statement');
        Route::get('/reports/general-ledger', [RegencyReportController::class, 'generalLedger'])->name('reports.general-ledger');
        Route::get('/reports/cash-flow', [RegencyReportController::class, 'cashFlow'])->name('reports.cash-flow');
        Route::get('/reports/calk', [RegencyReportController::class, 'calk'])->name('reports.calk');
    });

    Route::get('/search', SearchController::class)->name('search');

    Route::get('/billing/invoices', [TenantInvoiceController::class, 'index'])->name('billing.invoices.index');
    Route::get('/billing/invoices/{invoice}', [TenantInvoiceController::class, 'show'])->name('billing.invoices.show');
    Route::post('/billing/invoices/{invoice}/checkout/tripay', [TenantInvoiceController::class, 'checkoutTripay'])->name('billing.invoices.checkout.tripay');

    Route::get('/membership/members', [\App\Http\Controllers\Membership\MemberController::class, 'index'])->name('membership.members.index');
    Route::get('/membership/members/create', [\App\Http\Controllers\Membership\MemberController::class, 'create'])->name('membership.members.create');
    Route::post('/membership/members', [\App\Http\Controllers\Membership\MemberController::class, 'store'])->name('membership.members.store');
    Route::get('/membership/members/{member}', [\App\Http\Controllers\Membership\MemberController::class, 'show'])->name('membership.members.show');
    Route::get('/membership/members/{member}/edit', [\App\Http\Controllers\Membership\MemberController::class, 'edit'])->name('membership.members.edit');
    Route::put('/membership/members/{member}', [\App\Http\Controllers\Membership\MemberController::class, 'update'])->name('membership.members.update');

    Route::get('/membership/groups', [\App\Http\Controllers\Membership\GroupController::class, 'index'])->name('membership.groups.index');
    Route::get('/membership/groups/create', [\App\Http\Controllers\Membership\GroupController::class, 'create'])->name('membership.groups.create');
    Route::post('/membership/groups', [\App\Http\Controllers\Membership\GroupController::class, 'store'])->name('membership.groups.store');
    Route::get('/membership/groups/{group}', [\App\Http\Controllers\Membership\GroupController::class, 'show'])->name('membership.groups.show');
    Route::get('/membership/groups/{group}/edit', [\App\Http\Controllers\Membership\GroupController::class, 'edit'])->name('membership.groups.edit');
    Route::put('/membership/groups/{group}', [\App\Http\Controllers\Membership\GroupController::class, 'update'])->name('membership.groups.update');

    Route::get('/lending/proposals', [\App\Http\Controllers\Lending\LoanProposalController::class, 'index'])->name('lending.proposals.index');
    Route::get('/lending/proposals/create', [\App\Http\Controllers\Lending\LoanProposalController::class, 'create'])->name('lending.proposals.create');
    Route::post('/lending/proposals', [\App\Http\Controllers\Lending\LoanProposalController::class, 'store'])->name('lending.proposals.store');
    Route::get('/lending/proposals/{proposal}', [\App\Http\Controllers\Lending\LoanProposalController::class, 'show'])->name('lending.proposals.show');
    Route::get('/lending/proposals/{proposal}/edit', [\App\Http\Controllers\Lending\LoanProposalController::class, 'edit'])->name('lending.proposals.edit');
    Route::put('/lending/proposals/{proposal}', [\App\Http\Controllers\Lending\LoanProposalController::class, 'update'])->name('lending.proposals.update');
    Route::post('/lending/proposals/{proposal}/submit', [\App\Http\Controllers\Lending\LoanProposalController::class, 'submit'])->name('lending.proposals.submit');
    Route::post('/lending/proposals/{proposal}/approve', [\App\Http\Controllers\Lending\LoanProposalController::class, 'approve'])->name('lending.proposals.approve');
    Route::post('/lending/proposals/{proposal}/reject', [\App\Http\Controllers\Lending\LoanProposalController::class, 'reject'])->name('lending.proposals.reject');

    Route::get('/lending/loans', [\App\Http\Controllers\Lending\LoanController::class, 'index'])->name('lending.loans.index');
    Route::get('/lending/loans/{loan}', [\App\Http\Controllers\Lending\LoanController::class, 'show'])->name('lending.loans.show');
    Route::post('/lending/loans/{loan}/disburse', [\App\Http\Controllers\Lending\LoanController::class, 'disburse'])->name('lending.loans.disburse');

    Route::get('/lending/payments/create', [\App\Http\Controllers\Lending\LoanPaymentController::class, 'create'])->name('lending.payments.create');
    Route::post('/lending/payments', [\App\Http\Controllers\Lending\LoanPaymentController::class, 'store'])->name('lending.payments.store');

    Route::get('/lending/reports/portfolio', [\App\Http\Controllers\Lending\LoanReportController::class, 'portfolio'])->name('lending.reports.portfolio');

    Route::get('/accounting/journal-entries', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'index'])->name('accounting.journal-entries.index');
    Route::get('/accounting/journal-entries/create', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'create'])->name('accounting.journal-entries.create');
    Route::post('/accounting/journal-entries', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'store'])->name('accounting.journal-entries.store');
    Route::get('/accounting/journal-entries/{journalEntry}', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'show'])->name('accounting.journal-entries.show');
    Route::post('/accounting/journal-entries/{journalEntry}/post', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'post'])->name('accounting.journal-entries.post');
    Route::post('/accounting/journal-entries/{journalEntry}/reverse', [\App\Http\Controllers\Accounting\JournalEntryController::class, 'reverse'])->name('accounting.journal-entries.reverse');

    Route::get('/accounting/reports/balance-sheet', [\App\Http\Controllers\Accounting\ReportController::class, 'balanceSheet'])->name('accounting.reports.balance-sheet');
    Route::get('/accounting/reports/income-statement', [\App\Http\Controllers\Accounting\ReportController::class, 'incomeStatement'])->name('accounting.reports.income-statement');
    Route::get('/accounting/reports/general-ledger', [\App\Http\Controllers\Accounting\ReportController::class, 'generalLedger'])->name('accounting.reports.general-ledger');
    Route::get('/accounting/reports/cash-flow', [\App\Http\Controllers\Accounting\ReportController::class, 'cashFlow'])->name('accounting.reports.cash-flow');
    Route::get('/accounting/reports/calk', [\App\Http\Controllers\Accounting\ReportController::class, 'calk'])->name('accounting.reports.calk');
    Route::get('/accounting/reports/export/pdf', [\App\Http\Controllers\Accounting\ReportController::class, 'exportPdf'])->name('accounting.reports.export.pdf');

    Route::get('/accounting/accounts', [\App\Http\Controllers\Accounting\AccountController::class, 'index'])->name('accounting.accounts.index');
    Route::get('/accounting/accounts/create', [\App\Http\Controllers\Accounting\AccountController::class, 'create'])->name('accounting.accounts.create');
    Route::post('/accounting/accounts', [\App\Http\Controllers\Accounting\AccountController::class, 'store'])->name('accounting.accounts.store');
    Route::get('/accounting/accounts/{account}/edit', [\App\Http\Controllers\Accounting\AccountController::class, 'edit'])->name('accounting.accounts.edit');
    Route::put('/accounting/accounts/{account}', [\App\Http\Controllers\Accounting\AccountController::class, 'update'])->name('accounting.accounts.update');

    Route::get('/assets', [\App\Http\Controllers\AssetController::class, 'index'])->name('assets.index');
    Route::get('/assets/create', [\App\Http\Controllers\AssetController::class, 'create'])->name('assets.create');
    Route::post('/assets', [\App\Http\Controllers\AssetController::class, 'store'])->name('assets.store');
    Route::get('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'show'])->name('assets.show');
    Route::get('/assets/{asset}/edit', [\App\Http\Controllers\AssetController::class, 'edit'])->name('assets.edit');
    Route::put('/assets/{asset}', [\App\Http\Controllers\AssetController::class, 'update'])->name('assets.update');
    Route::post('/assets/{asset}/depreciate', [\App\Http\Controllers\AssetController::class, 'depreciate'])->name('assets.depreciate');
    Route::post('/assets/{asset}/dispose', [\App\Http\Controllers\AssetController::class, 'dispose'])->name('assets.dispose');
});

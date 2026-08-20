<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Holding\HoldingReportController;
use App\Http\Controllers\Api\Holding\HoldingTenantController;
use App\Http\Controllers\Assistant\AssistantToolController;
use Illuminate\Support\Facades\Route;

/*
| Tool callbacks from assistant orchestrator (server-to-server).
| Auth = HMAC signature + external_user_id -> platform user.
| Tenant resolved from host / optional X-Tenant-Code.
*/
Route::middleware(['orchestrator.signature', 'tenant', 'assistant.actor'])
    ->prefix('assistant/tools')
    ->group(function (): void {
        Route::post('/', AssistantToolController::class)->name('assistant.tools.dispatch');
        Route::post('/{tool}', AssistantToolController::class)
            ->where('tool', '[a-z0-9_]+')
            ->name('assistant.tools.run');
    });

/*
|--------------------------------------------------------------------------
| Holding Application Financial Reports API
|--------------------------------------------------------------------------
|
| Dedicated endpoints for holding / parent enterprise application to consume
| financial statements (Neraca, Laba Rugi, Arus Kas, CALK, Perubahan Ekuitas)
| per subsidiary tenant unit or consolidated.
|
*/
$registerHoldingRoutes = function (string $prefix): void {
    Route::middleware(['holding.auth'])
        ->prefix($prefix)
        ->group(function () use ($prefix): void {
            // Tenant / Subsidiary Directory
            Route::get('/tenants', [HoldingTenantController::class, 'index'])->name("api.{$prefix}.tenants.index");
            Route::get('/tenants/{tenant}', [HoldingTenantController::class, 'show'])->name("api.{$prefix}.tenants.show");

            // General / Query-based Financial Reports
            Route::prefix('reports')->group(function () use ($prefix): void {
                Route::get('/balance-sheet', [HoldingReportController::class, 'balanceSheet'])->name("api.{$prefix}.reports.balance-sheet");
                Route::get('/income-statement', [HoldingReportController::class, 'incomeStatement'])->name("api.{$prefix}.reports.income-statement");
                Route::get('/cash-flow', [HoldingReportController::class, 'cashFlow'])->name("api.{$prefix}.reports.cash-flow");
                Route::get('/calk', [HoldingReportController::class, 'calk'])->name("api.{$prefix}.reports.calk");
                Route::get('/equity-changes', [HoldingReportController::class, 'equityChanges'])->name("api.{$prefix}.reports.equity-changes");
                Route::get('/pack', [HoldingReportController::class, 'pack'])->name("api.{$prefix}.reports.pack");

                // Consolidated Reports
                Route::prefix('consolidated')->group(function () use ($prefix): void {
                    Route::get('/balance-sheet', [HoldingReportController::class, 'consolidatedBalanceSheet'])->name("api.{$prefix}.reports.consolidated.balance-sheet");
                    Route::get('/income-statement', [HoldingReportController::class, 'consolidatedIncomeStatement'])->name("api.{$prefix}.reports.consolidated.income-statement");
                    Route::get('/cash-flow', [HoldingReportController::class, 'consolidatedCashFlow'])->name("api.{$prefix}.reports.consolidated.cash-flow");
                    Route::get('/calk', [HoldingReportController::class, 'consolidatedCalk'])->name("api.{$prefix}.reports.consolidated.calk");
                    Route::get('/equity-changes', [HoldingReportController::class, 'consolidatedEquityChanges'])->name("api.{$prefix}.reports.consolidated.equity-changes");
                    Route::get('/pack', [HoldingReportController::class, 'consolidatedPack'])->name("api.{$prefix}.reports.consolidated.pack");
                });
            });

            // Tenant-scoped Financial Reports (/tenants/{tenant}/reports/...)
            Route::prefix('tenants/{tenant}/reports')->group(function () use ($prefix): void {
                Route::get('/balance-sheet', [HoldingReportController::class, 'balanceSheet'])->name("api.{$prefix}.tenants.reports.balance-sheet");
                Route::get('/income-statement', [HoldingReportController::class, 'incomeStatement'])->name("api.{$prefix}.tenants.reports.income-statement");
                Route::get('/cash-flow', [HoldingReportController::class, 'cashFlow'])->name("api.{$prefix}.tenants.reports.cash-flow");
                Route::get('/calk', [HoldingReportController::class, 'calk'])->name("api.{$prefix}.tenants.reports.calk");
                Route::get('/equity-changes', [HoldingReportController::class, 'equityChanges'])->name("api.{$prefix}.tenants.reports.equity-changes");
                Route::get('/pack', [HoldingReportController::class, 'pack'])->name("api.{$prefix}.tenants.reports.pack");
            });
        });
};

$registerHoldingRoutes('v1/holding');
$registerHoldingRoutes('holding');

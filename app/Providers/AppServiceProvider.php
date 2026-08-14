<?php

declare(strict_types=1);

namespace App\Providers;

use App\Assistant\EnpiiSessionResolver;
use App\Assistant\Handlers\CreateJournalEntryHandler;
use App\Assistant\Handlers\GetAssetHandler;
use App\Assistant\Handlers\GetLoanHandler;
use App\Assistant\Handlers\GroupsWithLoansHandler;
use App\Assistant\Handlers\ListAccountsHandler;
use App\Assistant\Handlers\ListDueBillingHandler;
use App\Assistant\Handlers\RecordInstallmentHandler;
use App\Assistant\Handlers\ReverseJournalHandler;
use App\Assistant\Handlers\SearchAssetsHandler;
use App\Assistant\Handlers\SearchGroupsHandler;
use App\Assistant\Handlers\SearchJournalsHandler;
use App\Assistant\Handlers\SearchLoansHandler;
use App\Assistant\Handlers\SearchMembersHandler;
use App\Assistant\Handlers\SendBillingNoticesHandler;
use App\Tenancy\TenantContext;
use Enpii\Assistant\Contracts\SessionResolver;
use Enpii\Assistant\Contracts\TenantResolver;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // enpii/assistant package bindings
        $this->app->bind(TenantResolver::class, fn () => new class implements TenantResolver
        {
            public function resolve(): string
            {
                $context = app(TenantContext::class);

                return $context->isInitialized() ? (string) $context->id() : '1';
            }
        });
        $this->app->bind(SessionResolver::class, EnpiiSessionResolver::class);

        // Tool handler registration. Resolved out of the container so each
        // handler gets AssistantToolService + dependencies injected.
        $this->app->afterResolving(ToolRegistry::class, function (ToolRegistry $registry): void {
            $registry->registerMany([
                $this->app->make(SearchMembersHandler::class),
                $this->app->make(SearchGroupsHandler::class),
                $this->app->make(GroupsWithLoansHandler::class),
                $this->app->make(SearchLoansHandler::class),
                $this->app->make(GetLoanHandler::class),
                $this->app->make(ListAccountsHandler::class),
                $this->app->make(SearchJournalsHandler::class),
                $this->app->make(SearchAssetsHandler::class),
                $this->app->make(GetAssetHandler::class),
                $this->app->make(ListDueBillingHandler::class),
                $this->app->make(CreateJournalEntryHandler::class),
                $this->app->make(ReverseJournalHandler::class),
                $this->app->make(RecordInstallmentHandler::class),
                $this->app->make(SendBillingNoticesHandler::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}

<?php

declare(strict_types=1);

namespace Enpii\Assistant;

use Enpii\Assistant\Console\CompactConversationsCommand;
use Enpii\Assistant\Console\ExpirePendingConfirmationsCommand;
use Enpii\Assistant\Console\ReindexKnowledgeCommand;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\Chat\Embedder;
use Enpii\Assistant\Services\Chat\ModelGateway;
use Enpii\Assistant\Services\Rag\Bm25;
use Enpii\Assistant\Services\Rag\Chunker;
use Enpii\Assistant\Services\Rag\DocumentIngestService;
use Enpii\Assistant\Services\Rag\DocumentLoader;
use Enpii\Assistant\Services\Rag\FaqRetriever;
use Enpii\Assistant\Services\Rag\HybridSearch;
use Enpii\Assistant\Services\Rag\OcrRunner;
use Enpii\Assistant\Services\Tools\SafeHttpClient;
use Enpii\Assistant\Services\Tools\ToolExecutor;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Enpii\Assistant\Services\Tools\WebTools;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/assistant.php', 'assistant');
        $this->mergeConfigFrom(__DIR__.'/../config/llm.php', 'assistant-llm');
        $this->mergeConfigFrom(__DIR__.'/../config/rag.php', 'assistant-rag');
        $this->mergeConfigFrom(__DIR__.'/../config/web_tools.php', 'web_tools');

        // Singletons — same instance reused across the request lifecycle.
        $this->app->singleton(Embedder::class, ModelGateway::class);
        $this->app->singleton(ModelGateway::class);
        $this->app->singleton(SafeHttpClient::class);
        $this->app->singleton(WebTools::class);
        $this->app->singleton(Bm25::class);
        $this->app->singleton(Chunker::class);
        $this->app->singleton(OcrRunner::class);
        $this->app->singleton(DocumentLoader::class);
        $this->app->singleton(HybridSearch::class);
        $this->app->singleton(FaqRetriever::class);
        $this->app->singleton(DocumentIngestService::class);
        $this->app->singleton(ToolRegistry::class);
        $this->app->singleton(ToolExecutor::class);
        $this->app->singleton(AgentLoop::class);

        $this->app->singleton('assistant', fn () => $this->app->make(ToolRegistry::class));
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->bindModelsToRagConnection();

        if ($this->app->runningInConsole()) {
            $this->commands([
                CompactConversationsCommand::class,
                ExpirePendingConfirmationsCommand::class,
                ReindexKnowledgeCommand::class,
            ]);

            $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule->command('assistant:expire-pending-confirmations')
                    ->hourly()
                    ->withoutOverlapping();
                $schedule->command('assistant:compact-conversations')
                    ->hourly()
                    ->withoutOverlapping();
            });
        }

        $this->ensureLlmConfig();
    }

    /**
     * Defensive fallback for HTTP workers that loaded dotenv into process env
     * but missed the config repository (long-lived queues, Octane, etc).
     */
    private function ensureLlmConfig(): void
    {
        $key = trim((string) (config('assistant-llm.api_key')
            ?: env('OPENAI_API_KEY')
            ?: getenv('OPENAI_API_KEY')
            ?: ($_ENV['OPENAI_API_KEY'] ?? '')
            ?: ($_SERVER['OPENAI_API_KEY'] ?? '')));
        if ($key !== '' && trim((string) config('assistant-llm.api_key')) === '') {
            config(['assistant-llm.api_key' => $key]);
        }

        $base = trim((string) (config('assistant-llm.base_url')
            ?: env('OPENAI_BASE_URL')
            ?: getenv('OPENAI_BASE_URL')
            ?: ''));
        if ($base !== '' && trim((string) config('assistant-llm.base_url')) === '') {
            config(['assistant-llm.base_url' => rtrim($base, '/')]);
        }
    }

    /**
     * Tell every ai_* model (via TargetsRagConnection trait) to route queries
     * to the dedicated `rag` connection. The trait overrides getConnectionName()
     * at runtime, so we don't have to touch any model file when toggling
     * Postgres on/off. If RAG_DB_CONNECTION is unset (sqlite tests, etc),
     * we leave the property null and models stay on the default driver.
     */
    private function bindModelsToRagConnection(): void
    {
        if ((string) env('RAG_DB_CONNECTION', '') === '') {
            return;
        }

        self::$ragConnectionName = 'rag';
    }

    public static ?string $ragConnectionName = null;
}

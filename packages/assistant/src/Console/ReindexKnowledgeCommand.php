<?php

declare(strict_types=1);

namespace Enpii\Assistant\Console;

use Illuminate\Console\Command;
use Enpii\Assistant\Services\Rag\FaqRetriever;

final class ReindexKnowledgeCommand extends Command
{
    protected $signature = 'assistant:reindex {--tenant= : Tenant id to reindex} {--force : Re-embed even existing chunks}';

    protected $description = 'Re-embed all document chunks for RAG retrieval';

    public function handle(): int
    {
        $tenant = $this->option('tenant') ?: null;
        $force = (bool) $this->option('force');
        $updated = app(FaqRetriever::class)->reindex($tenant, $force);

        $this->info("Reindexed {$updated} chunks.");

        return self::SUCCESS;
    }
}

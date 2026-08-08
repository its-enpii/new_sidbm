<?php

declare(strict_types=1);

namespace Enpii\Assistant\Models\Concerns;

use Enpii\Assistant\AssistantServiceProvider;

/**
 * Routes this model's queries to the dedicated `rag` connection when the
 * host app has configured one (via RAG_DB_CONNECTION env). Otherwise models
 * stay on the default Eloquent connection (sqlite/mysql) and embeddings are
 * stored as JSON in `embedding_json`.
 *
 * The override is intentionally cheap: we only swap connection name when
 * AssistantServiceProvider actually published one, so test suites and
 * sqlite-only environments keep working with zero config.
 */
trait TargetsRagConnection
{
    public function getConnectionName(): ?string
    {
        $rag = AssistantServiceProvider::$ragConnectionName;

        return $rag !== null && $rag !== '' ? $rag : parent::getConnectionName();
    }
}

# enpii/assistant (in-process)

A Laravel package extracted from the orchestrator at `enpii/assistant`. Runs **in-process** within the host application — no separate container, no HTTP hop, no HMAC.

## What it does

- Tool-use chat loop with SSE streaming (`AgentLoop`)
- OpenAI-compatible LLM gateway (`ModelGateway`) — chat + embed
- RAG pipeline (`FaqRetriever`, `HybridSearch`, `DocumentIngestService`)
- Built-in web tools (`web_search`, `web_fetch`)
- Tool handler registry — host app supplies concrete handlers via `ToolHandler` interface
- Append-only audit log
- Scheduled compaction & expiry sweeps

## Installation

```bash
# in host app's composer.json
"repositories": [
  { "type": "path", "url": "./packages/enpii/assistant", "options": { "symlink": true } }
],
"require": { "arafi/enpii/assistant": "@dev" }
```

```bash
composer require arafi/enpii/assistant:@dev
php artisan migrate
```

## Host-app bindings (required)

In your `AppServiceProvider::register()`:

```php
// 1. Resolve current tenant id from your context
$this->app->bind(\Enpii\Assistant\Contracts\TenantResolver::class, function () {
    return app(\App\Tenancy\TenantContext::class)->id();
});

// 2. Resolve current user/session for persona + external_user_id
$this->app->bind(
    \Enpii\Assistant\Contracts\SessionResolver::class,
    \App\Assistant\SidbmSessionResolver::class,
);

// 3. Register tool handlers (one per tool)
app(\Enpii\Assistant\Services\Tools\ToolRegistry::class)->registerMany([
    app(\App\Assistant\Handlers\SearchMembersHandler::class),
    // ...
]);
```

## Routes

```php
// routes/web.php
Route::prefix('assistant')->group(function () {
    Route::post('/chat', [\Enpii\Assistant\Http\Controllers\ChatController::class, 'store']);
    Route::post('/confirmations/{executionId}', [\Enpii\Assistant\Http\Controllers\ConfirmationController::class, 'store'])
        ->whereUuid('executionId');
});
```

## Configuration

Publish with `php artisan vendor:publish --tag=enpii/assistant-config`. Required env vars (already used today):

```
OPENAI_BASE_URL=https://ai.enpiistudio.com/v1
OPENAI_API_KEY=...
LLM_MODEL=ag/gemini-3-flash
EMBEDDING_BASE_URL=http://ollama:11434/v1
EMBEDDING_MODEL=nomic-embed-text
EMBEDDING_DIM=768
```

## SSE event format (for widget)

Same as legacy orchestrator:

| event | data |
|---|---|
| `conversation` | `{id}` |
| `text` | `{delta}` |
| `tool_use` | `{id, name, input}` |
| `tool_result` | `{id, name, ok, output}` |
| `confirmation_required` | `{execution_id, action, summary, plan, warnings, options, proposed_params}` |
| `result` | `{conversation_id, status}` |
| `error` | `{message}` |

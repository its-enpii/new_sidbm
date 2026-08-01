# SIDBM Assistant Orchestrator (target repo)

Scaffold target: sibling repo `../sidbm-assistant` (Laravel 13 + Inertia).

This folder holds **bootstrap specs** until `composer create-project` / full scaffold runs outside the blocked shell.

## Env (orchestrator)

```env
APP_URL=http://localhost:8100
OPENAI_BASE_URL=https://ai.enpiistudio.com/v1
OPENAI_API_KEY=
LLM_MODEL=gpt-4o-mini
EMBEDDING_MODEL=text-embedding-3-small
ASSISTANT_SHARED_SECRET=tk_same_as_sidbm
SIDBM_APP_URL=http://host.docker.internal:8080
SIDBM_TENANT_CODE=local
SESSION_TOKEN_TTL=3600
CORS_ALLOWED_ORIGINS=http://localhost:8080
```

## SIDBM env (done in this repo)

```env
ASSISTANT_ORCHESTRATOR_BASE_URL=http://host.docker.internal:8100
ASSISTANT_ORCHESTRATOR_PUBLIC_URL=http://localhost:8100
ASSISTANT_SHARED_SECRET=tk_same_as_here
ASSISTANT_WIDGET_ENABLED=true
```

## API contract

### POST /api/v1/sessions
Auth: `Authorization: Bearer {ASSISTANT_SHARED_SECRET}`  
Body: `{ external_user_id, display_name?, tenant_code?, channel: "web" }`  
→ `{ session_token, expires_at, public_base_url, conversation_id? }`

### POST /api/v1/chat
Auth: `Bearer {session_token}`  
Body: `{ conversation_id?, message }`  
→ SSE: `conversation` | `text` | `tool_use` | `tool_result` | `confirmation_required` | `error` | `result`

### POST /api/v1/confirmations/{execution_id}
Body: `{ decision: "approve"|"reject", allocation_choice? }` → SSE resume

### Tool HMAC → SIDBM
```
secret = sha256(ASSISTANT_SHARED_SECRET)
X-Orchestrator-Signature = HMAC-SHA256(ts + '.' + rawBody, secret)
X-Orchestrator-Timestamp = ms
POST {SIDBM}/api/assistant/tools/{tool}
```

## Modules to implement in new repo

1. `ModelGateway` — OpenAI-compat stream + tool calls  
2. `AgentLoop` — RAG inject + tool loop + confirm pause  
3. `ToolExecutor` — HMAC client  
4. `SessionController` / `ChatController` / `ConfirmationController`  
5. Migrations: tenants, api_keys, chat_sessions, conversations, messages, tools, tool_executions, confirmations, knowledge_*, audit_logs  
6. `SidbmPilotSeeder` — import `php artisan sidbm:assistant-tools` JSON  
7. Admin FAQ CRUD (Inertia)

## Status

- [x] SIDBM adapter cutover (config, client, HMAC, session-token, native widget, docs)
- [x] Laravel scaffold at `F:/workspace/laragon/www/sidbm-assistant`
- [x] Core: sessions, chat SSE, AgentLoop, ToolExecutor, FAQ, seeder 13 tools
- [ ] E2E pilot with real `OPENAI_API_KEY` + both apps running

Run orchestrator:

```bash
cd ../sidbm-assistant
cp .env.example .env   # set OPENAI_API_KEY + ASSISTANT_SHARED_SECRET
php artisan key:generate
touch database/database.sqlite
php artisan migrate --force
php artisan db:seed --class=SidbmPilotSeeder
php artisan serve --port=8100
```


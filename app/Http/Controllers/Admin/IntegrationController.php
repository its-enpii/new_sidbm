<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Settings\OrchestratorRequest;
use App\Services\PlatformSettingService;
use App\Tenancy\TenantContext;
use Enpii\Assistant\Models\AuditLog;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Models\KnowledgeSource;
use Enpii\Assistant\Models\Persona;
use Enpii\Assistant\Models\Tool;
use Enpii\Assistant\AssistantServiceProvider;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\Rag\DocumentIngestService;
use Enpii\Assistant\Services\SseEmitter;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Integration page — full control over AI Assistant:
 * Personas, Tools, RAG Documents, Test Chat, Conversations, & Audit Logs.
 */
final class IntegrationController
{
    /**
     * Return a connection-qualified table name for validation rules so they
     * run against the same database the model actually uses (rag vs default).
     */
    private function qualifiedTable(string $table): string
    {
        $conn = AssistantServiceProvider::$ragConnectionName;

        return ($conn !== null && $conn !== '') ? "{$conn}.{$table}" : $table;
    }

    private function resolveTenantId(Request $request): string
    {
        /** @var TenantContext $tenancy */
        $tenancy = app(TenantContext::class);
        if ($tenancy->isInitialized()) {
            return (string) $tenancy->id();
        }

        if ($request->filled('tenant_id')) {
            return (string) $request->input('tenant_id');
        }

        return '1';
    }

    public function index(PlatformSettingService $settings, ToolRegistry $registry, Request $request): Response
    {

        $personas = Persona::query()
            ->with('tools:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (Persona $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'system_prompt' => $p->system_prompt,
                'is_default' => (bool) $p->is_default,
                'is_active' => (bool) $p->is_active,
                'tools' => $p->tools->map(fn (Tool $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                ]),
                'tools_count' => $p->tools->count(),
                'created_at' => optional($p->created_at)->toIso8601String(),
            ]);

        $registeredNames = collect($registry->all())->pluck('name')->all();

        $tools = Tool::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tool $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'json_schema' => $t->json_schema,
                'requires_confirmation' => (bool) $t->requires_confirmation,
                'is_active' => (bool) $t->is_active,
                'is_registered' => in_array($t->name, $registeredNames, true),
                'created_at' => optional($t->created_at)->toIso8601String(),
            ]);

        // Check if registry has handlers not yet in ai_tools DB
        $dbToolNames = $tools->pluck('name')->all();
        $unregisteredHandlers = array_filter(
            $registry->all(),
            static fn ($h) => ! in_array($h->name(), $dbToolNames, true)
        );

        $stats = [
            'total_personas' => Persona::query()->count(),
            'active_personas' => Persona::query()->where('is_active', true)->count(),
            'total_tools' => Tool::query()->count(),
            'active_tools' => Tool::query()->where('is_active', true)->count(),
            'unregistered_code_tools' => count($unregisteredHandlers),
            'total_documents' => Document::query()->count(),
            'total_conversations' => Conversation::query()->count(),
        ];

        return Inertia::render('Admin/Integration', [
            'orchestrator' => [
                'in_process' => true,
                'configured' => true,
                'has_secret' => false,
            ],
            'personas' => $personas,
            'tools' => $tools,
            'stats' => $stats,
        ]);
    }

    public function update(OrchestratorRequest $request, PlatformSettingService $settings): RedirectResponse
    {
        return back()->with('success', ['message' => 'Pengaturan tersimpan (no-op: orchestrator berjalan in-process).', 'tab' => 'orchestrator']);
    }

    public function test(AgentLoop $loop, TenantContext $tenancy, Request $request): JsonResponse
    {
        $started = microtime(true);

        try {
            $text = app(\Enpii\Assistant\Services\Chat\ModelGateway::class)
                ->complete([
                    ['role' => 'system', 'content' => 'You are a health probe. Reply with exactly one short Indonesian word.'],
                    ['role' => 'user', 'content' => 'reply singkat'],
                ], [])['content'] ?? '';

            return response()->json([
                'success' => $text !== '',
                'status' => $text !== '' ? 'connected' : 'empty_response',
                'message' => $text !== '' ? 'Asisten in-process terhubung.' : 'LLM merespons kosong.',
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
                'expires_at' => null,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            ]);
        }
    }

    public function chat(Request $request, AgentLoop $loop, TenantContext $tenancy): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'string', 'max:64'],
            'persona_slug' => ['nullable', 'string', 'max:64'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        return response()->stream(function () use ($loop, $tenantId, $data): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                @ob_end_flush();
            }

            $sse = new class extends SseEmitter {
                public function emit(string $event, array $data): void
                {
                    echo 'event: '.$event."\n";
                    foreach (explode("\n", json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) as $line) {
                        echo 'data: '.$line."\n";
                    }
                    echo "\n";
                    @ob_flush();
                    @flush();
                }
            };

            try {
                $persona = null;
                if (! empty($data['persona_slug'])) {
                    $persona = Persona::findBySlug((string) $data['persona_slug']);
                }

                $personaId = $persona?->id;
                $conversation = Conversation::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'persona_id' => $personaId,
                        'external_user_id' => 'admin-test-'.bin2hex(random_bytes(4)),
                        'status' => 'open',
                    ],
                    [
                        'channel' => 'admin-test',
                        'last_activity_at' => now(),
                        'started_at' => now(),
                    ],
                );

                $loop->run(
                    tenantId: $tenantId,
                    externalUserId: (string) $conversation->external_user_id,
                    personaId: $personaId,
                    conversation: $conversation,
                    userMessage: (string) $data['message'],
                    sse: $sse,
                );
            } catch (\Throwable $e) {
                echo 'event: error'."\n";
                echo 'data: '.json_encode(['message' => $e->getMessage()])."\n\n";
                @ob_flush();
                @flush();
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    // ==================== PERSONAS CRUD ====================

    public function personas(Request $request): JsonResponse
    {
        $personas = Persona::query()
            ->with('tools:id,name')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(static fn (Persona $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'system_prompt' => $p->system_prompt,
                'is_default' => (bool) $p->is_default,
                'is_active' => (bool) $p->is_active,
                'tools' => $p->tools->pluck('id')->all(),
                'tools_count' => $p->tools->count(),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $personas->count(),
            'personas' => $personas,
        ]);
    }

    public function storePersona(Request $request): JsonResponse
    {
        $personasTable = $this->qualifiedTable('ai_personas');
        $toolsTable = $this->qualifiedTable('ai_tools');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:100', "unique:{$personasTable},slug"],
            'system_prompt' => ['nullable', 'string', 'max:10000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['array'],
            'tool_ids.*' => ['uuid', "exists:{$toolsTable},id"],
        ]);

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);
        if (Persona::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::random(4);
        }

        if (! empty($data['is_default'])) {
            Persona::query()->update(['is_default' => false]);
        }

        $persona = Persona::query()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'system_prompt' => $data['system_prompt'] ?? '',
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (isset($data['tool_ids'])) {
            $persona->tools()->sync($data['tool_ids']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Persona berhasil dibuat.',
            'persona' => $persona->fresh('tools'),
        ]);
    }

    public function updatePersona(string $id, Request $request): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        $personasTable = $this->qualifiedTable('ai_personas');
        $toolsTable = $this->qualifiedTable('ai_tools');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:100', "unique:{$personasTable},slug,{$id}"],
            'system_prompt' => ['nullable', 'string', 'max:10000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['array'],
            'tool_ids.*' => ['uuid', "exists:{$toolsTable},id"],
        ]);

        if (! empty($data['is_default']) && ! $persona->is_default) {
            Persona::query()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $persona->forceFill([
            'name' => $data['name'],
            'slug' => Str::slug($data['slug']),
            'system_prompt' => $data['system_prompt'] ?? '',
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ])->save();

        if (isset($data['tool_ids'])) {
            $persona->tools()->sync($data['tool_ids']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Persona berhasil diperbarui.',
            'persona' => $persona->fresh('tools'),
        ]);
    }

    public function deletePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        if ($persona->is_default) {
            return response()->json([
                'ok' => false,
                'message' => 'Tidak dapat menghapus persona default. Atur persona lain sebagai default terlebih dahulu.',
            ], 422);
        }

        $persona->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Persona berhasil dihapus.',
        ]);
    }

    public function togglePersonaStatus(string $id, Request $request): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $field = $request->input('field', 'is_active');

        if ($field === 'is_default') {
            Persona::query()->update(['is_default' => false]);
            $persona->forceFill(['is_default' => true, 'is_active' => true])->save();
        } else {
            $persona->forceFill(['is_active' => ! $persona->is_active])->save();
        }

        return response()->json([
            'ok' => true,
            'persona' => $persona,
        ]);
    }

    // ==================== TOOLS CONTROL & SYNC ====================

    public function tools(Request $request, ToolRegistry $registry): JsonResponse
    {
        $registeredNames = collect($registry->all())->pluck('name')->all();

        $tools = Tool::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tool $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'json_schema' => $t->json_schema,
                'requires_confirmation' => (bool) $t->requires_confirmation,
                'is_active' => (bool) $t->is_active,
                'is_registered' => in_array($t->name, $registeredNames, true),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $tools->count(),
            'tools' => $tools,
        ]);
    }

    public function syncTools(ToolRegistry $registry): JsonResponse
    {
        $synced = 0;
        foreach ($registry->all() as $handler) {
            Tool::query()->updateOrCreate(
                ['name' => $handler->name()],
                [
                    'description' => $handler->description(),
                    'json_schema' => $handler->jsonSchema(),
                    'requires_confirmation' => $handler->requiresConfirmation(),
                    'is_active' => true,
                ]
            );
            $synced++;
        }

        return response()->json([
            'ok' => true,
            'message' => "Berhasil menyinkronkan {$synced} tool handler dari registry.",
            'synced_count' => $synced,
        ]);
    }

    public function updateTool(string $id, Request $request): JsonResponse
    {
        $tool = Tool::query()->findOrFail($id);

        $data = $request->validate([
            'requires_confirmation' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if (isset($data['requires_confirmation'])) {
            $tool->requires_confirmation = (bool) $data['requires_confirmation'];
        }

        if (isset($data['is_active'])) {
            $tool->is_active = (bool) $data['is_active'];
        }

        if (isset($data['description'])) {
            $tool->description = $data['description'];
        }

        $tool->save();

        return response()->json([
            'ok' => true,
            'message' => 'Pengaturan tool berhasil diperbarui.',
            'tool' => $tool,
        ]);
    }

    // ==================== RAG DOCUMENTS MANAGEMENT ====================

    public function upload(Request $request, DocumentIngestService $ingest): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:200'],
            'persona_id' => ['nullable', 'uuid'],
            'file' => ['required', 'file', 'max:20480', 'mimetypes:text/plain,text/markdown,text/html,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        ]);

        $tenantId = $this->resolveTenantId($request);

        try {
            $doc = $ingest->ingestFile(
                tenantId: $tenantId,
                absolutePath: $data['file']->getRealPath(),
                declaredFormat: (string) $data['file']->getClientOriginalExtension(),
                title: $data['title'] ?? null,
                personaId: $data['persona_id'] ?? null,
            );

            return response()->json([
                'ok' => true,
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'format' => $doc->source_format,
                    'content_length' => $doc->content_raw ? mb_strlen($doc->content_raw) : 0,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function documents(Request $request): JsonResponse
    {
        $data = $request->validate([
            'persona_id' => ['nullable', 'uuid'],
        ]);

        $items = Document::query()
            ->with('source.persona')
            ->withCount('chunks')
            ->whereHas('source', function ($q) use ($data): void {
                $q->where('status', 'active');
                if (! empty($data['persona_id'])) {
                    $q->where('persona_id', $data['persona_id']);
                }
            })
            ->orderByDesc('created_at')
            ->get(['id', 'knowledge_source_id', 'title', 'content_raw', 'source_format', 'created_at'])
            ->map(static fn (Document $d): array => [
                'id' => $d->id,
                'title' => $d->title ?: '(untitled)',
                'format' => $d->source_format,
                'preview' => $d->content_raw ? mb_substr($d->content_raw, 0, 200) : '',
                'content_length' => $d->content_raw ? mb_strlen($d->content_raw) : 0,
                'chunks_count' => (int) ($d->chunks_count ?? 0),
                'persona_id' => optional($d->source)->persona_id,
                'persona_name' => optional(optional($d->source)->persona)->name,
                'created_at' => optional($d->created_at)->toIso8601String(),
            ])
            ->values();

        return response()->json([
            'ok' => true,
            'count' => $items->count(),
            'items' => $items,
        ]);
    }

    public function documentDetail(string $id): JsonResponse
    {
        $doc = Document::query()
            ->with(['source.persona', 'chunks'])
            ->findOrFail($id);

        return response()->json([
            'ok' => true,
            'document' => [
                'id' => $doc->id,
                'title' => $doc->title,
                'format' => $doc->source_format,
                'content_raw' => $doc->content_raw,
                'created_at' => optional($doc->created_at)->toIso8601String(),
                'persona_name' => optional(optional($doc->source)->persona)->name,
                'chunks' => $doc->chunks->map(fn ($c) => [
                    'id' => $c->id,
                    'chunk_index' => $c->chunk_index,
                    'chunk_text' => $c->chunk_text,
                    'has_embedding' => ! empty($c->embedding_json),
                ]),
            ],
        ]);
    }

    public function deleteDocument(string $id): JsonResponse
    {
        $doc = Document::query()->with('source')->findOrFail($id);
        $source = $doc->source;

        $doc->delete();
        if ($source && $source->documents()->count() === 0) {
            $source->delete();
        }

        return response()->json([
            'ok' => true,
            'message' => 'Dokumen berhasil dihapus dari Knowledge Base.',
        ]);
    }

    // ==================== CONVERSATIONS & AUDIT LOGS ====================

    public function conversations(Request $request): JsonResponse
    {
        $conversations = Conversation::query()
            ->with('persona:id,name')
            ->withCount('messages')
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'persona_name' => optional($c->persona)->name ?? 'Default',
                'channel' => $c->channel,
                'status' => $c->status,
                'messages_count' => $c->messages_count,
                'started_at' => optional($c->started_at)->toIso8601String(),
                'last_activity_at' => optional($c->last_activity_at)->toIso8601String(),
            ]);

        return response()->json([
            'ok' => true,
            'count' => $conversations->count(),
            'conversations' => $conversations,
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $logs = AuditLog::query()
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'actor' => $log->actor,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'metadata' => $log->metadata,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ]);

        return response()->json([
            'ok' => true,
            'count' => $logs->count(),
            'logs' => $logs,
        ]);
    }
}

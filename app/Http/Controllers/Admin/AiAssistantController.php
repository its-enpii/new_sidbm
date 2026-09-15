<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlatformSettingService;
use App\Tenancy\TenantContext;
use Enpii\Assistant\AssistantServiceProvider;
use Enpii\Assistant\Models\AuditLog;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Models\Persona;
use Enpii\Assistant\Models\Tool;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\Rag\DocumentIngestService;
use Enpii\Assistant\Services\SseEmitter;
use Enpii\Assistant\Services\Tools\ToolRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AiAssistantController extends Controller
{
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

    public function index(ToolRegistry $registry, Request $request, PlatformSettingService $platformSettings): Response
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

        $registeredNames = array_map(static fn ($h): string => $h->name(), $registry->all());

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

        $globalAiEnabled = (bool) $platformSettings->get('ai.enabled', true);

        return Inertia::render('Admin/AiAssistant/Index', [
            'personas' => $personas,
            'tools' => $tools,
            'stats' => $stats,
            'global_ai_enabled' => $globalAiEnabled,
        ]);
    }

    public function personas(): JsonResponse
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

        return response()->json(['ok' => true, 'personas' => $personas]);
    }

    public function tools(ToolRegistry $registry): JsonResponse
    {
        $registeredNames = array_map(static fn ($h): string => $h->name(), $registry->all());

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

        return response()->json(['ok' => true, 'tools' => $tools]);
    }

    public function storePersona(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50'],
            'system_prompt' => ['required', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['string'],
        ]);

        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (! empty($data['is_default'])) {
            Persona::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $toolIds = $data['tool_ids'] ?? [];
        unset($data['tool_ids']);

        $persona = Persona::query()->create($data);
        if (! empty($toolIds)) {
            $persona->tools()->sync($toolIds);
        }

        return response()->json(['ok' => true, 'persona' => $persona->load('tools:id,name')]);
    }

    public function updatePersona(Request $request, string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:50'],
            'system_prompt' => ['sometimes', 'required', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['string'],
        ]);

        if (! empty($data['is_default'])) {
            Persona::query()->where('id', '!=', $id)->where('is_default', true)->update(['is_default' => false]);
        }

        if (array_key_exists('tool_ids', $data)) {
            $persona->tools()->sync($data['tool_ids'] ?? []);
            unset($data['tool_ids']);
        }

        $persona->update($data);

        return response()->json(['ok' => true, 'persona' => $persona->fresh(['tools:id,name'])]);
    }

    public function deletePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $persona->tools()->detach();
        $persona->delete();

        return response()->json(['ok' => true]);
    }

    public function togglePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $persona->is_active = ! $persona->is_active;
        $persona->save();

        return response()->json(['ok' => true, 'is_active' => $persona->is_active]);
    }

    public function syncTools(ToolRegistry $registry): JsonResponse
    {
        $registered = $registry->all();
        $synced = [];

        foreach ($registered as $handler) {
            $tool = Tool::query()->updateOrCreate(
                ['name' => $handler->name()],
                [
                    'description' => $handler->description(),
                    'json_schema' => $handler->parametersSchema(),
                    'requires_confirmation' => $handler->requiresConfirmation(),
                ]
            );
            $synced[] = $tool->name;
        }

        return response()->json(['ok' => true, 'synced' => $synced]);
    }

    public function updateTool(Request $request, string $id): JsonResponse
    {
        $tool = Tool::query()->findOrFail($id);

        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'requires_confirmation' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'string'],
        ]);

        $tool->update($data);

        return response()->json(['ok' => true, 'tool' => $tool->fresh()]);
    }

    public function documents(Request $request): JsonResponse
    {
        $query = Document::query();

        if ($request->filled('namespace')) {
            $query->where('namespace', $request->input('namespace'));
        }

        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->input('search').'%');
        }

        $docs = $query->latest()
            ->paginate((int) $request->input('per_page', 20))
            ->through(fn (Document $d) => [
                'id' => $d->id,
                'namespace' => $d->namespace,
                'title' => $d->title,
                'source_type' => $d->source_type,
                'source_url' => $d->source_url,
                'chunk_count' => $d->chunk_count,
                'token_count' => $d->token_count,
                'meta' => $d->meta,
                'created_at' => optional($d->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'documents' => $docs]);
    }

    public function documentDetail(string $id): JsonResponse
    {
        $doc = Document::query()->with('chunks')->findOrFail($id);

        return response()->json(['ok' => true, 'document' => $doc]);
    }

    public function deleteDocument(string $id): JsonResponse
    {
        $doc = Document::query()->findOrFail($id);
        $doc->chunks()->delete();
        $doc->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadDocument(Request $request, DocumentIngestService $ingestService): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,docx,txt,md,csv'],
            'title' => ['nullable', 'string', 'max:255'],
            'namespace' => ['nullable', 'string', 'max:100'],
        ]);

        $file = $request->file('file');
        $title = $request->input('title', $file->getClientOriginalName());
        $namespace = $request->input('namespace', 'default');
        $tenantId = $this->resolveTenantId($request);

        $doc = $ingestService->ingestUploadedFile(
            file: $file,
            title: $title,
            namespace: $namespace,
            tenantId: $tenantId,
        );

        return response()->json(['ok' => true, 'document_id' => $doc->id, 'chunks' => $doc->chunk_count]);
    }

    public function chatStream(Request $request, AgentLoop $agentLoop, SseEmitter $sse): StreamedResponse
    {
        $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => ['nullable', 'string'],
            'persona_id' => ['nullable', 'string'],
        ]);

        $message = (string) $request->input('message');
        $conversationId = $request->input('conversation_id');
        $personaId = $request->input('persona_id');
        $tenantId = $this->resolveTenantId($request);
        $userId = (string) ($request->user()?->row_id ?? '1');

        return response()->stream(function () use ($agentLoop, $sse, $message, $conversationId, $personaId, $tenantId, $userId): void {
            $sse->init();

            try {
                $agentLoop->run(
                    message: $message,
                    conversationId: $conversationId,
                    personaId: $personaId,
                    tenantId: $tenantId,
                    userId: $userId,
                    onToken: fn (string $token) => $sse->event('token', ['delta' => $token]),
                    onToolStart: fn (string $tool, array $args) => $sse->event('tool_start', ['tool' => $tool, 'args' => $args]),
                    onToolEnd: fn (string $tool, mixed $res) => $sse->event('tool_end', ['tool' => $tool, 'result' => $res]),
                    onDone: fn (string $reply, string $convId) => $sse->event('done', ['reply' => $reply, 'conversation_id' => $convId]),
                    onError: fn (string $err) => $sse->event('error', ['message' => $err]),
                );
            } catch (\Throwable $e) {
                $sse->event('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        $conversations = Conversation::query()
            ->where('tenant_id', $tenantId)
            ->latest('updated_at')
            ->paginate((int) $request->input('per_page', 20))
            ->through(fn (Conversation $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'persona_id' => $c->persona_id,
                'user_id' => $c->user_id,
                'created_at' => optional($c->created_at)->toIso8601String(),
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'conversations' => $conversations]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($request->filled('tenant_id')) {
            $query->where('tenant_id', $request->input('tenant_id'));
        }

        if ($request->filled('tool_name')) {
            $query->where('tool_name', $request->input('tool_name'));
        }

        $logs = $query->latest()
            ->paginate((int) $request->input('per_page', 50))
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'conversation_id' => $l->conversation_id,
                'user_id' => $l->user_id,
                'tenant_id' => $l->tenant_id,
                'tool_name' => $l->tool_name,
                'action_status' => $l->action_status,
                'tokens_used' => $l->tokens_used,
                'duration_ms' => $l->duration_ms,
                'error_message' => $l->error_message,
                'created_at' => optional($l->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'audit_logs' => $logs]);
    }
}

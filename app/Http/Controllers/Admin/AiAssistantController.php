<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\OrchestratorRequest;
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

    public function index(ToolRegistry $registry, Request $request): Response
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

        return Inertia::render('Admin/AiAssistant/Index', [
            'personas' => $personas,
            'tools' => $tools,
            'stats' => $stats,
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

    public function storePersona(OrchestratorRequest $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120'],
            'system_prompt' => ['required', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_ids' => ['array'],
            'tool_ids.*' => ['uuid'],
        ]);

        $slug = ! empty($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['name']);

        if (! empty($data['is_default'])) {
            Persona::query()->update(['is_default' => false]);
        }

        /** @var Persona $persona */
        $persona = Persona::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'system_prompt' => $data['system_prompt'],
            'is_default' => $data['is_default'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['tool_ids'])) {
            $persona->tools()->sync($data['tool_ids']);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Persona berhasil dibuat.',
            'persona' => $persona->fresh('tools'),
        ]);
    }

    public function updatePersona(OrchestratorRequest $request, string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'slug' => ['sometimes', 'required', 'string', 'max:120'],
            'system_prompt' => ['sometimes', 'required', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'tool_ids' => ['nullable', 'array'],
            'tool_ids.*' => ['uuid'],
        ]);

        if (! empty($data['is_default'])) {
            Persona::query()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $persona->update(array_filter([
            'name' => $data['name'] ?? null,
            'slug' => isset($data['slug']) ? Str::slug($data['slug']) : null,
            'system_prompt' => $data['system_prompt'] ?? null,
            'is_default' => $data['is_default'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], static fn ($v) => $v !== null));

        if (array_key_exists('tool_ids', $data)) {
            $persona->tools()->sync($data['tool_ids'] ?? []);
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
        $persona->delete();

        return response()->json(['ok' => true, 'message' => 'Persona berhasil dihapus.']);
    }

    public function togglePersona(Request $request, string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        $field = (string) $request->input('field', 'is_active');
        if (! in_array($field, ['is_active', 'is_default'], true)) {
            return response()->json(['ok' => false, 'message' => 'Field tidak valid.'], 422);
        }

        if ($field === 'is_default' && ! $persona->is_default) {
            Persona::query()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $persona->update([$field => ! $persona->{$field}]);

        return response()->json([
            'ok' => true,
            'message' => 'Status persona diperbarui.',
            'persona' => $persona->fresh(),
        ]);
    }

    public function syncTools(ToolRegistry $registry): JsonResponse
    {
        $handlers = $registry->all();
        $synced = 0;

        foreach ($handlers as $h) {
            Tool::query()->updateOrCreate(
                ['name' => $h->name()],
                [
                    'description' => $h->description(),
                    'json_schema' => $h->jsonSchema(),
                    'requires_confirmation' => $h->requiresConfirmation(),
                    'is_active' => true,
                ]
            );
            $synced++;
        }

        return response()->json([
            'ok' => true,
            'message' => "Sinkronisasi selesai. {$synced} tools ter-register.",
        ]);
    }

    public function updateTool(Request $request, string $id): JsonResponse
    {
        $tool = Tool::query()->findOrFail($id);
        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'requires_confirmation' => ['sometimes', 'boolean'],
        ]);

        $tool->update($data);

        return response()->json(['ok' => true, 'message' => 'Tool diperbarui.', 'tool' => $tool]);
    }

    public function uploadDocument(Request $request, DocumentIngestService $ingestService): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,txt,md,doc,docx,csv,json', 'max:20480'],
            'title' => ['nullable', 'string', 'max:255'],
        ]);

        $uploaded = $request->file('file');
        if (! $uploaded) {
            return response()->json(['ok' => false, 'message' => 'File tidak ditemukan.'], 422);
        }

        $tenantId = $this->resolveTenantId($request);
        $title = $request->input('title') ?: $uploaded->getClientOriginalName();

        try {
            $doc = $ingestService->ingestUploadedFile($uploaded, $tenantId, $title);

            return response()->json([
                'ok' => true,
                'message' => "Dokumen '{$title}' berhasil diproses dan disimpan ke Knowledge Base.",
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'chunks_count' => $doc->chunks()->count(),
                    'created_at' => optional($doc->created_at)->toIso8601String(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memproses dokumen: '.$e->getMessage(),
            ], 500);
        }
    }

    public function documents(Request $request): JsonResponse
    {
        $docs = Document::query()
            ->withCount('chunks')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn (Document $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'status' => $d->status,
                'chunks_count' => $d->chunks_count,
                'created_at' => optional($d->created_at)->toIso8601String(),
            ]);

        return response()->json(['ok' => true, 'documents' => $docs]);
    }

    public function documentDetail(string $id): JsonResponse
    {
        $doc = Document::query()->with('chunks')->findOrFail($id);

        return response()->json([
            'ok' => true,
            'document' => [
                'id' => $doc->id,
                'title' => $doc->title,
                'status' => $doc->status,
                'metadata' => $doc->metadata,
                'chunks' => $doc->chunks->map(fn ($c) => [
                    'id' => $c->id,
                    'chunk_index' => $c->chunk_index,
                    'content' => $c->content,
                ]),
                'created_at' => optional($doc->created_at)->toIso8601String(),
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

    public function chatStream(Request $request, AgentLoop $loop): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'persona_slug' => ['nullable', 'string'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $tenantId = $this->resolveTenantId($request);
        $user = $request->user();
        $actorId = $user ? (string) $user->row_id : '0';

        $personaSlug = $data['persona_slug'] ?? null;
        $persona = $personaSlug
            ? Persona::query()->where('slug', $personaSlug)->where('is_active', true)->first()
            : Persona::query()->where('is_default', true)->where('is_active', true)->first();

        if (! $persona) {
            $persona = Persona::query()->where('is_active', true)->orderBy('id')->first();
        }

        $conversationId = $data['conversation_id'] ?? null;
        if ($conversationId) {
            $conversation = Conversation::query()->find($conversationId);
        } else {
            $conversation = Conversation::query()->create([
                'tenant_id' => $tenantId,
                'user_id' => $actorId,
                'persona_id' => optional($persona)->id,
                'channel' => 'web_test',
                'status' => 'active',
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);
        }

        return SseEmitter::stream(function (SseEmitter $emitter) use ($loop, $conversation, $persona, $data, $actorId): void {
            $emitter->emit('start', [
                'conversation_id' => $conversation->id,
                'persona' => optional($persona)->name,
            ]);

            try {
                $loop->run(
                    conversation: $conversation,
                    persona: $persona,
                    userMessageText: $data['message'],
                    actorId: $actorId,
                    onToken: function (string $token) use ($emitter): void {
                        $emitter->emit('text', ['delta' => $token]);
                    },
                    onToolStart: function (string $name, array $input) use ($emitter): void {
                        $emitter->emit('tool_start', ['name' => $name, 'input' => $input]);
                    },
                    onToolEnd: function (string $name, mixed $result) use ($emitter): void {
                        $emitter->emit('tool_end', ['name' => $name, 'result' => $result]);
                    }
                );

                $emitter->emit('done', ['status' => 'completed']);
            } catch (\Throwable $e) {
                $emitter->emit('error', ['message' => $e->getMessage()]);
            }
        });
    }
}

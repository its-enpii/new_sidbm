<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\OrchestratorRequest;
use App\Services\Billing\DuitkuClient;
use App\Services\Billing\TripayClient;
use App\Services\PlatformSettingService;
use App\Tenancy\TenantContext;
use Enpii\Assistant\AssistantServiceProvider;
use Enpii\Assistant\Models\AuditLog;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Document;
use Enpii\Assistant\Models\KnowledgeSource;
use Enpii\Assistant\Models\Persona;
use Enpii\Assistant\Models\Tool;
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
 * Admin Integration page — control panel for Tripay Payment Gateway, WhatsApp, and AI Assistant.
 */
final class IntegrationController extends Controller
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
            'active_gateway' => (string) ($settings->get('billing.active_gateway') ?: 'duitku'),
            'duitku' => [
                'merchant_code' => (string) ($settings->get('duitku.merchant_code') ?? config('duitku.merchant_code', '')),
                'has_api_key' => ! empty($settings->getEncrypted('duitku.api_key') ?? config('duitku.api_key')),
                'mode' => (string) ($settings->get('duitku.mode') ?? config('duitku.mode', 'sandbox')),
                'default_method' => (string) ($settings->get('duitku.default_method') ?? config('duitku.default_method', 'VC')),
            ],
            'tripay' => [
                'merchant_code' => (string) ($settings->get('tripay.merchant_code') ?? config('tripay.merchant_code', '')),
                'has_api_key' => ! empty($settings->getEncrypted('tripay.api_key') ?? config('tripay.api_key')),
                'has_private_key' => ! empty($settings->getEncrypted('tripay.private_key') ?? config('tripay.private_key')),
                'mode' => (string) ($settings->get('tripay.mode') ?? config('tripay.mode', 'sandbox')),
                'default_method' => (string) ($settings->get('tripay.default_method') ?? config('tripay.default_method', 'QRIS2')),
            ],
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

    public function updateTripay(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_code' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'private_key' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', 'string', 'in:sandbox,production'],
            'default_method' => ['required', 'string', 'max:50'],
        ]);

        $settings->set('tripay.merchant_code', trim($validated['merchant_code']));
        $settings->set('tripay.mode', $validated['mode']);
        $settings->set('tripay.default_method', $validated['default_method']);

        if (! empty($validated['api_key'])) {
            $settings->setEncrypted('tripay.api_key', trim($validated['api_key']));
        }

        if (! empty($validated['private_key'])) {
            $settings->setEncrypted('tripay.private_key', trim($validated['private_key']));
        }

        $settings->flush();

        return redirect()->back()->with('success', 'Kredensial & konfigurasi Tripay Payment Gateway berhasil disimpan.');
    }

    public function testTripay(TripayClient $tripay): JsonResponse
    {
        try {
            $channels = $tripay->getPaymentChannels();

            return response()->json([
                'ok' => true,
                'message' => sprintf('Koneksi ke Tripay API (%s) BERHASIL! Menemukan %d saluran pembayaran.', config('tripay.mode'), count($channels)),
                'channels' => $channels,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal terhubung ke Tripay: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function updateDuitku(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'merchant_code' => ['required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'mode' => ['required', 'string', 'in:sandbox,production'],
            'default_method' => ['required', 'string', 'max:50'],
        ]);

        $settings->set('duitku.merchant_code', trim($validated['merchant_code']));
        $settings->set('duitku.mode', $validated['mode']);
        $settings->set('duitku.default_method', $validated['default_method']);

        if (! empty($validated['api_key'])) {
            $settings->setEncrypted('duitku.api_key', trim($validated['api_key']));
        }

        $settings->flush();

        return redirect()->back()->with('success', 'Kredensial & konfigurasi Duitku Payment Gateway berhasil disimpan.');
    }

    public function testDuitku(DuitkuClient $duitku): JsonResponse
    {
        try {
            $channels = $duitku->getPaymentChannels();

            return response()->json([
                'ok' => true,
                'message' => sprintf('Koneksi ke Duitku API (%s) BERHASIL! Menemukan %d saluran pembayaran.', $duitku->getMode(), count($channels)),
                'channels' => $channels,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal terhubung ke Duitku: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function updateActiveGateway(Request $request, PlatformSettingService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'gateway' => ['required', 'string', 'in:tripay,duitku'],
        ]);

        $settings->set('billing.active_gateway', $validated['gateway']);
        $settings->flush();

        return redirect()->back()->with('success', sprintf('Payment Gateway utama berhasil diubah menjadi %s.', strtoupper($validated['gateway'])));
    }

    public function update(OrchestratorRequest $request, PlatformSettingService $settings): RedirectResponse
    {
        return back()->with('success', ['message' => 'Pengaturan tersimpan (no-op: orchestrator berjalan in-process).', 'tab' => 'orchestrator']);
    }

    public function test(AgentLoop $loop, TenantContext $tenancy, Request $request): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'persona_slug' => ['nullable', 'string'],
        ]);

        $actor = $request->user();
        $tenantId = $this->resolveTenantId($request);
        $persona = ! empty($data['persona_slug'])
            ? Persona::query()->where('slug', $data['persona_slug'])->first()
            : Persona::query()->where('is_default', true)->first();

        return response()->stream(function () use ($loop, $data, $actor, $persona, $tenantId): void {
            SseEmitter::headers();

            try {
                $convo = Conversation::query()->create([
                    'tenant_id' => $tenantId,
                    'persona_id' => $persona?->id,
                    'actor_user_id' => (int) $actor->row_id,
                    'actor_role' => 'superadmin',
                    'channel' => 'admin_test',
                    'status' => 'active',
                    'started_at' => now(),
                    'last_activity_at' => now(),
                ]);

                $userMsg = $convo->messages()->create([
                    'tenant_id' => $tenantId,
                    'role' => 'user',
                    'content' => $data['message'],
                ]);

                $loop->run(
                    conversation: $convo,
                    userMessage: $userMsg,
                    actor: $actor,
                    persona: $persona,
                    onChunk: function (string $text): void {
                        SseEmitter::emit('chunk', ['text' => $text]);
                    },
                    onToolCall: function (string $toolName, array $args): void {
                        SseEmitter::emit('tool_call', ['tool' => $toolName, 'args' => $args]);
                    },
                    onToolResult: function (string $toolName, array $result): void {
                        SseEmitter::emit('tool_result', ['tool' => $toolName, 'result' => $result]);
                    },
                    onConfirmationRequired: function (string $toolName, array $args, string $confId): void {
                        SseEmitter::emit('confirmation_required', [
                            'tool' => $toolName,
                            'args' => $args,
                            'confirmation_id' => $confId,
                        ]);
                    },
                );

                SseEmitter::emit('done', ['status' => 'completed']);
            } catch (\Throwable $e) {
                SseEmitter::emit('error', ['message' => $e->getMessage()]);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function createPersona(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'system_prompt' => ['required', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'tool_names' => ['array'],
            'tool_names.*' => ['string'],
        ]);

        $slug = Str::slug($data['name']);
        if (Persona::query()->where('slug', $slug)->exists()) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        if (! empty($data['is_default'])) {
            Persona::query()->update(['is_default' => false]);
        }

        $persona = Persona::query()->create([
            'slug' => $slug,
            'name' => $data['name'],
            'system_prompt' => $data['system_prompt'],
            'is_default' => ! empty($data['is_default']),
            'is_active' => $data['is_active'] ?? true,
        ]);

        if (! empty($data['tool_names'])) {
            $toolIds = Tool::query()->whereIn('name', $data['tool_names'])->pluck('id')->all();
            $persona->tools()->sync($toolIds);
        }

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$persona->name}' berhasil dibuat.",
            'persona' => [
                'id' => $persona->id,
                'slug' => $persona->slug,
                'name' => $persona->name,
                'system_prompt' => $persona->system_prompt,
                'is_default' => (bool) $persona->is_default,
                'is_active' => (bool) $persona->is_active,
                'tools' => $persona->tools->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                'tools_count' => $persona->tools->count(),
            ],
        ]);
    }

    public function updatePersona(Request $request, string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'system_prompt' => ['sometimes', 'string'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'tool_names' => ['nullable', 'array'],
            'tool_names.*' => ['string'],
        ]);

        if (array_key_exists('is_default', $data) && $data['is_default']) {
            Persona::query()->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $persona->update(array_filter($data, static fn ($k) => $k !== 'tool_names', ARRAY_FILTER_USE_KEY));

        if (array_key_exists('tool_names', $data)) {
            $toolIds = ! empty($data['tool_names'])
                ? Tool::query()->whereIn('name', $data['tool_names'])->pluck('id')->all()
                : [];
            $persona->tools()->sync($toolIds);
        }

        $persona->load('tools:id,name');

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$persona->name}' berhasil diperbarui.",
            'persona' => [
                'id' => $persona->id,
                'slug' => $persona->slug,
                'name' => $persona->name,
                'system_prompt' => $persona->system_prompt,
                'is_default' => (bool) $persona->is_default,
                'is_active' => (bool) $persona->is_active,
                'tools' => $persona->tools->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]),
                'tools_count' => $persona->tools->count(),
            ],
        ]);
    }

    public function deletePersona(string $id): JsonResponse
    {
        $persona = Persona::query()->findOrFail($id);
        if ($persona->is_default) {
            return response()->json(['ok' => false, 'message' => 'Tidak dapat menghapus persona default.'], 422);
        }

        $name = $persona->name;
        $persona->tools()->detach();
        $persona->delete();

        return response()->json([
            'ok' => true,
            'message' => "Persona '{$name}' berhasil dihapus.",
        ]);
    }

    public function syncTools(ToolRegistry $registry): JsonResponse
    {
        $handlers = $registry->all();
        $synced = 0;

        foreach ($handlers as $handler) {
            Tool::query()->updateOrCreate(
                ['name' => $handler->name()],
                [
                    'description' => $handler->description(),
                    'json_schema' => json_encode($handler->jsonSchema()),
                    'requires_confirmation' => $handler->requiresConfirmation(),
                    'is_active' => true,
                ]
            );
            $synced++;
        }

        return response()->json([
            'ok' => true,
            'message' => "Berhasil menyinkronkan {$synced} tool handlers dari kode ke database.",
            'count' => $synced,
        ]);
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

        return response()->json([
            'ok' => true,
            'message' => "Tool '{$tool->name}' diperbarui.",
            'tool' => [
                'id' => $tool->id,
                'name' => $tool->name,
                'description' => $tool->description,
                'requires_confirmation' => (bool) $tool->requires_confirmation,
                'is_active' => (bool) $tool->is_active,
            ],
        ]);
    }

    public function uploadDocument(Request $request, DocumentIngestService $ingest, TenantContext $tenancy): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'title' => ['nullable', 'string', 'max:255'],
            'persona_id' => ['nullable', 'uuid'],
        ]);

        $file = $request->file('file');
        $tenantId = $this->resolveTenantId($request);
        $personaId = $request->input('persona_id');

        try {
            $doc = $ingest->ingestUploadedFile(
                file: $file,
                tenantId: $tenantId,
                personaId: $personaId !== '' ? $personaId : null,
                title: $request->input('title') ?: $file->getClientOriginalName(),
            );

            return response()->json([
                'ok' => true,
                'message' => "Dokumen '{$doc->title}' berhasil diunggah & diproses ke Vector DB ({$doc->chunks()->count()} chunks).",
                'document' => [
                    'id' => $doc->id,
                    'title' => $doc->title,
                    'chunks_count' => $doc->chunks()->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Gagal memproses dokumen: '.$e->getMessage(),
            ], 422);
        }
    }

    public function syncKnowledgeSources(TenantContext $tenancy, DocumentIngestService $ingest): JsonResponse
    {
        $tenantId = $tenancy->isInitialized() ? (string) $tenancy->id() : '1';

        $manuals = [
            [
                'title' => 'Panduan SOP Transaksi Jurnal Umum SIDBM',
                'file' => base_path('docs/PERBANDINGAN_SIDBM_LEGACY_VS_NEXT.md'),
            ],
            [
                'title' => 'Dokumentasi Sistem & Database Sharding',
                'file' => base_path('docs/DATABASE_STRUCTURE.md'),
            ],
        ];

        $imported = 0;
        foreach ($manuals as $m) {
            if (file_exists($m['file'])) {
                try {
                    $ingest->ingestLocalFile(
                        filePath: $m['file'],
                        tenantId: $tenantId,
                        personaId: null,
                        title: $m['title'],
                    );
                    $imported++;
                } catch (\Throwable $e) {
                    // Skip failed file
                }
            }
        }

        return response()->json([
            'ok' => true,
            'message' => "Berhasil menyinkronkan {$imported} dokumen SOP sistem ke Vector Store.",
        ]);
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

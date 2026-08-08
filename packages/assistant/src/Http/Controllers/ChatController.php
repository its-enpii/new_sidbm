<?php

declare(strict_types=1);

namespace Enpii\Assistant\Http\Controllers;

use Illuminate\Http\Request;
use Enpii\Assistant\Contracts\SessionResolver;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Persona;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\SseEmitter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ChatController
{
    public function __construct(
        private readonly AgentLoop $loop,
        private readonly SessionResolver $sessions,
    ) {
    }

    public function store(Request $request): StreamedResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:8000'],
            'conversation_id' => ['nullable', 'uuid'],
        ]);

        $ctx = $this->sessions->resolve();
        $tenantId = (string) $ctx['tenant_id'];
        $externalUserId = (string) $ctx['external_user_id'];

        $personaId = null;
        if (! empty($ctx['persona_slug'])) {
            $personaId = Persona::findBySlug((string) $ctx['persona_slug'])?->id;
        }

        $conversation = $this->resolveOrCreateConversation(
            $tenantId,
            $externalUserId,
            $personaId,
            $data['conversation_id'] ?? null,
        );

        return $this->sse(function (SseEmitter $sse) use ($tenantId, $externalUserId, $personaId, $conversation, $data): void {
            $this->loop->run($tenantId, $externalUserId, $personaId, $conversation, $data['message'], $sse);
        });
    }

    private function resolveOrCreateConversation(string $tenantId, string $externalUserId, ?string $personaId, ?string $id): Conversation
    {
        if ($id) {
            $existing = Conversation::query()
                ->where('id', $id)
                ->where('external_user_id', $externalUserId)
                ->first();
            if ($existing !== null) {
                if ($existing->last_activity_at && $existing->last_activity_at->lt(now()->subMinutes(30))) {
                    $existing->forceFill(['status' => 'closed', 'ended_at' => now()])->save();
                } elseif ($personaId && $existing->persona_id && $existing->persona_id !== $personaId) {
                    $existing->forceFill(['status' => 'closed', 'ended_at' => now()])->save();
                } else {
                    if ($existing->persona_id === null && $personaId) {
                        $existing->forceFill(['persona_id' => $personaId])->save();
                    }

                    return $existing;
                }
            }
        }

        return Conversation::query()->create([
            'tenant_id' => $tenantId,
            'persona_id' => $personaId,
            'external_user_id' => $externalUserId,
            'channel' => 'web',
            'status' => 'open',
            'last_activity_at' => now(),
            'started_at' => now(),
        ]);
    }

    private function sse(callable $callback): StreamedResponse
    {
        return response()->stream(function () use ($callback): void {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $sse = new SseEmitter;
            $callback($sse);
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
<?php

declare(strict_types=1);

namespace Enpii\Assistant\Http\Controllers;

use Enpii\Assistant\Contracts\SessionResolver;
use Enpii\Assistant\Models\ToolExecution;
use Enpii\Assistant\Services\Chat\AgentLoop;
use Enpii\Assistant\Services\SseEmitter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ConfirmationController
{
    public function __construct(
        private readonly AgentLoop $loop,
        private readonly SessionResolver $sessions,
    ) {}

    public function store(Request $request, string $executionId): StreamedResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'allocation_choice' => ['nullable', 'string', 'max:64'],
        ]);

        $ctx = $this->sessions->resolve();
        $tenantId = $ctx['tenant_id'];
        $externalUserId = $ctx['external_user_id'];

        $execution = ToolExecution::query()->where('id', $executionId)->firstOrFail();

        return response()->stream(function () use ($tenantId, $externalUserId, $execution, $data): void {
            @ini_set('zlib.output_compression', '0');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            $sse = new SseEmitter;
            $this->loop->confirm(
                $tenantId,
                $externalUserId,
                $execution,
                $data['decision'],
                $sse,
                $data['allocation_choice'] ?? null,
            );
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}

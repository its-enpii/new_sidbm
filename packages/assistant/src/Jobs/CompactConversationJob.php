<?php

declare(strict_types=1);

namespace Enpii\Assistant\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Enpii\Assistant\Models\Conversation;
use Enpii\Assistant\Models\Message;
use Enpii\Assistant\Services\Chat\ModelGateway;

final class CompactConversationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $conversationId,
    ) {
    }

    public function handle(ModelGateway $llm): void
    {
        $conversation = Conversation::query()->find($this->conversationId);
        if ($conversation === null) {
            return;
        }

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->orderBy('created_at')
            ->get();

        if ($messages->count() < 20) {
            return;
        }

        $transcript = $messages->map(fn (Message $m) => sprintf('[%s] %s', $m->role, (string) $m->content))
            ->implode("\n");

        $summary = trim((string) $llm->complete([
            ['role' => 'system', 'content' => 'Kamu meringkas percakapan operasional koperasi. Output dalam Bahasa Indonesia, ringkas, daftar poin keputusan penting + data angka saja. Maks 600 karakter.'],
            ['role' => 'user', 'content' => $transcript],
        ], [])['content']);

        if ($summary === '') {
            return;
        }

        $conversation->forceFill(['summary' => $summary])->save();
    }
}

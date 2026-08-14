<?php

declare(strict_types=1);

namespace Enpii\Assistant\Console;

use Enpii\Assistant\Jobs\CompactConversationJob;
use Enpii\Assistant\Models\Conversation;
use Illuminate\Console\Command;

final class CompactConversationsCommand extends Command
{
    protected $signature = 'assistant:compact-conversations {--dry-run : List candidates without queueing} {--idle-minutes=60 : Only conversations idle for at least this long}';

    protected $description = 'Queue compaction jobs for long-running conversations';

    public function handle(): int
    {
        $idle = max(0, (int) $this->option('idle-minutes'));
        $cutoff = now()->subMinutes($idle);

        $window = (int) config('assistant.chat_window', 20);

        $query = Conversation::query()
            ->where('status', 'open')
            ->where('last_activity_at', '<', $cutoff)
            ->withCount('messages')
            ->having('messages_count', '>=', $window);

        $rows = $query->limit(200)->get();
        $this->info("Found {$rows->count()} conversations idle ≥ {$idle}m with ≥ {$window} messages.");

        if ($this->option('dry-run')) {
            foreach ($rows as $r) {
                $this->line(" - {$r->id} (messages={$r->messages_count})");
            }

            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            CompactConversationJob::dispatch($r->id);
        }
        $this->info("Queued {$rows->count()} jobs.");

        return self::SUCCESS;
    }
}

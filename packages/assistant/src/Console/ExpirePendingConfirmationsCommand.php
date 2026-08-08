<?php

declare(strict_types=1);

namespace Enpii\Assistant\Console;

use Illuminate\Console\Command;
use Enpii\Assistant\Models\ToolExecution;

final class ExpirePendingConfirmationsCommand extends Command
{
    protected $signature = 'assistant:expire-pending-confirmations {--minutes=15 : Auto-cancel pending_confirmation older than this}';

    protected $description = 'Cancel stale tool executions awaiting user confirmation';

    public function handle(): int
    {
        $minutes = max(1, (int) $this->option('minutes'));
        $cutoff = now()->subMinutes($minutes);

        $expired = ToolExecution::query()
            ->where('status', 'pending_confirmation')
            ->where('requested_at', '<', $cutoff)
            ->get();

        foreach ($expired as $exec) {
            $exec->forceFill([
                'status' => 'cancelled',
                'executed_at' => now(),
            ])->save();
            $exec->confirmation?->forceFill([
                'status' => 'cancelled',
                'confirmed_at' => now(),
            ])->save();
        }

        $this->info("Cancelled {$expired->count()} pending confirmations older than {$minutes}m.");

        return self::SUCCESS;
    }
}

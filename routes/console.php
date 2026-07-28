<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Backup is intentionally not scheduled here; the existing server cron remains authoritative.
// Add application-level housekeeping schedules below when needed.

Schedule::command('model:prune')->dailyAt('02:30');

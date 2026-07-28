<?php

declare(strict_types=1);

use App\Http\Controllers\Assistant\AssistantToolController;
use Illuminate\Support\Facades\Route;

/*
| Tool callbacks from encompletion (server-to-server).
| Auth = HMAC signature + external_user_id → platform user.
| Tenant resolved from host / optional X-Tenant-Code.
*/
Route::middleware(['encompletion.signature', 'tenant', 'assistant.actor'])
    ->prefix('assistant/tools')
    ->group(function (): void {
        Route::post('/', AssistantToolController::class)->name('assistant.tools.dispatch');
        Route::post('/{tool}', AssistantToolController::class)
            ->where('tool', '[a-z0-9_]+')
            ->name('assistant.tools.run');
    });

<?php

declare(strict_types=1);

use Enpii\SidbmAssistant\Http\Controllers\AssistantToolController;
use Enpii\SidbmAssistant\Http\Controllers\SessionTokenController;
use Enpii\SidbmAssistant\Http\Middleware\ResolveAssistantActor;
use Enpii\SidbmAssistant\Http\Middleware\VerifyOrchestratorSignature;
use Illuminate\Support\Facades\Route;

$toolsPrefix = trim((string) config('assistant.routes.tools_prefix', 'api/assistant/tools'), '/');
$sessionRoute = trim((string) config('assistant.routes.session_route', 'api/assistant/session-token'), '/');

Route::middleware(['web'])->group(function () use ($sessionRoute) {
    Route::post($sessionRoute, SessionTokenController::class)
        ->name('assistant.session-token');
});

Route::post($toolsPrefix.'/{tool}', AssistantToolController::class)
    ->middleware([VerifyOrchestratorSignature::class, ResolveAssistantActor::class])
    ->name('assistant.tools.invoke');

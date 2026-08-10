<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Enpii\Assistant\Http\Controllers\ChatController;
use Enpii\Assistant\Http\Controllers\ConfirmationController;
use Enpii\Assistant\Http\Controllers\PersonaInfoController;

// Host app registers these under a prefix and authentication middleware:
//   Route::middleware(['web', 'auth'])->prefix('assistant')->group(function () {
//       require base_path('packages/assistant/routes/api.php');
//   });

Route::get('/persona', [PersonaInfoController::class, 'show']);
Route::post('/chat', [ChatController::class, 'store']);
Route::post('/confirmations/{executionId}', [ConfirmationController::class, 'store'])
    ->whereUuid('executionId');
<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureSuperadmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResolveAssistantActor;
use App\Http\Middleware\VerifyEncompletionSignature;
use App\Tenancy\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'tripay/callback',
        ]);

        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'superadmin' => EnsureSuperadmin::class,
            'encompletion.signature' => VerifyEncompletionSignature::class,
            'assistant.actor' => ResolveAssistantActor::class,
        ]);

        $middleware->prependToPriorityList(SubstituteBindings::class, ResolveTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Register project-specific exception reporting here.
    })
    ->create();

<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Orchestrator connection
    |--------------------------------------------------------------------------
    | base_url         = origin of the orchestrator server (host)
    | public_url       = origin the browser widget uses for the WS endpoint
    |                    (set only when behind a reverse proxy / CDN)
    | shared_secret    = HS256 secret; key_hash = sha256(this)
    | adapter_base_url = default tenant app origin the orchestrator will call
    |                    back into (tool endpoint)
    */

    'base_url' => env('ASSISTANT_BASE_URL'),
    'public_url' => env('ASSISTANT_PUBLIC_URL'),
    'adapter_base_url' => rtrim((string) env('ASSISTANT_ADAPTER_BASE_URL', env('APP_URL', '')), '/'),
    'shared_secret' => (string) env('ASSISTANT_SHARED_SECRET', env('ASSISTANT_TENANT_API_KEY', '')),
    'timeout' => (int) env('ASSISTANT_TIMEOUT', 10),

    /*
    |--------------------------------------------------------------------------
    | Signature window (milliseconds)
    |--------------------------------------------------------------------------
    | Reject callbacks with timestamps beyond ± skew.
    */

    'signature_max_skew_ms' => (int) env('ASSISTANT_SIGNATURE_MAX_SKEW_MS', 5 * 60 * 1000),

    /*
    |--------------------------------------------------------------------------
    | Widget toggle
    |--------------------------------------------------------------------------
    */

    'widget_enabled' => (bool) env('ASSISTANT_WIDGET_ENABLED', false),
    'default_persona_slug' => (string) env('ASSISTANT_DEFAULT_PERSONA_SLUG', env('ASSISTANT_PERSONA_SLUG', '')),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */

    'routes' => [
        'session_route' => env('ASSISTANT_SESSION_ROUTE', 'api/assistant/session-token'),
        'tools_prefix' => env('ASSISTANT_TOOLS_PREFIX', 'api/assistant/tools'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Tools (resolved from the container)
    |--------------------------------------------------------------------------
    | Fully qualified class names. Each must implement
    | Enpii\SidbmAssistant\Contracts\AssistantToolHandler.
    */

    'tools' => [
        // \App\AssistantTools\GetUserTool::class,
    ],
];
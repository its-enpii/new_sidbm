<?php

declare(strict_types=1);

return [
    /**
     * Server-to-server base (SIDBM app → orchestrator).
     * Local Docker: http://host.docker.internal:8100
     */
    'base_url' => rtrim((string) env('ASSISTANT_ORCHESTRATOR_BASE_URL', env('ASSISTANT_BASE_URL', '')), '/'),

    /**
     * Browser-facing orchestrator origin (SSE chat). Defaults to base_url.
     */
    'public_url' => rtrim((string) env(
        'ASSISTANT_ORCHESTRATOR_PUBLIC_URL',
        env('ASSISTANT_PUBLIC_URL', env('ASSISTANT_ORCHESTRATOR_BASE_URL', env('ASSISTANT_BASE_URL', ''))),
    ), '/'),

    /**
     * Shared secret with orchestrator.
     * 1) SIDBM mints chat session via Bearer this key (server-side only).
     * 2) Orchestrator signs tool callbacks with HMAC secret = sha256(this plaintext).
     */
    'shared_secret' => (string) env('ASSISTANT_SHARED_SECRET', env('ASSISTANT_TENANT_API_KEY', '')),

    /**
     * Base URL the orchestrator uses to POST tool calls back to this SIDBM.
     * Must be reachable from the orchestrator process (not only the browser).
     * Empty → fall back to APP_URL.
     * Host orchestrator + Docker SIDBM: http://127.0.0.1:8080
     * Orchestrator in Docker + SIDBM on host: http://host.docker.internal:8080
     */
    'adapter_base_url' => rtrim((string) env('ASSISTANT_ADAPTER_BASE_URL', env('APP_URL', '')), '/'),

    /** Optional default persona slug when widget does not pass ?persona= */
    'default_persona_slug' => (string) env('ASSISTANT_PERSONA_SLUG', ''),

    /** Max age of X-Orchestrator-Timestamp (ms). */
    'signature_max_skew_ms' => (int) env('ASSISTANT_SIG_MAX_SKEW_MS', 300_000),

    'widget_enabled' => filter_var(env('ASSISTANT_WIDGET_ENABLED', false), FILTER_VALIDATE_BOOL),

    'timeout' => (int) env('ASSISTANT_TIMEOUT', 10),
];

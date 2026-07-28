<?php

declare(strict_types=1);

return [
    /**
     * Server-to-server base (from SIDBM app container → encompletion).
     * Local Docker: http://host.docker.internal:8010
     */
    'base_url' => rtrim((string) env('ENCOMPLETION_BASE_URL', ''), '/'),
    /**
     * Browser-facing base for widget.js + SSE. Defaults to base_url.
     * Local: http://localhost:8010
     */
    'public_url' => rtrim((string) env('ENCOMPLETION_PUBLIC_URL', env('ENCOMPLETION_BASE_URL', '')), '/'),
    /**
     * Tenant API key plaintext (tk_...). Used to:
     *  1) POST /api/embed/token (Authorization: Bearer)
     *  2) Verify inbound tool HMAC — encompletion signs with sha256(plaintext)
     *     as the HMAC secret (see tool-executor pickSigningKey).
     */
    'tenant_api_key' => (string) env('ENCOMPLETION_TENANT_API_KEY', ''),
    /** Max age of X-Encompletion-Timestamp (ms). */
    'signature_max_skew_ms' => (int) env('ENCOMPLETION_SIG_MAX_SKEW_MS', 300_000),
    'widget_enabled' => filter_var(env('ENCOMPLETION_WIDGET_ENABLED', false), FILTER_VALIDATE_BOOL),
    'timeout' => (int) env('ENCOMPLETION_TIMEOUT', 10),
];

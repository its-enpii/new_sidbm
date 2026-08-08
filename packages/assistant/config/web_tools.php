<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('WEB_TOOLS_ENABLED', true),
    'allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WEB_TOOLS_ALLOWLIST', '')),
    ))),
    'fetch_timeout' => (int) env('WEB_TOOLS_FETCH_TIMEOUT', 15),
    'fetch_max_bytes' => (int) env('WEB_TOOLS_FETCH_MAX_BYTES', 500_000),
    'search_provider' => (string) env('WEB_SEARCH_PROVIDER', 'brave'),
    'brave_api_key' => (string) env('BRAVE_API_KEY', ''),
    'user_agent' => (string) env('WEB_TOOLS_USER_AGENT', 'EnpiiAssistant/1.0'),
];

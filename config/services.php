<?php

declare(strict_types=1);

return [
    'regional_api' => [
        'base_url' => env('REGIONAL_CODE_API_URL', 'https://api.kodewilayah.web.id'),
        'timeout' => (int) env('REGIONAL_CODE_API_TIMEOUT', 10),
    ],
    'evolution' => [
        'url' => rtrim((string) env('EVOLUTION_URL', ''), '/'),
        'api_key' => (string) env('EVOLUTION_API_KEY', ''),
        'timeout' => (int) env('EVOLUTION_TIMEOUT', 15),
        'instance_prefix' => (string) env('EVOLUTION_INSTANCE_PREFIX', 'sidbm'),
    ],
    'wa_gateway' => [
        'base_url' => rtrim((string) env('WA_GATEWAY_BASE', env('EVOLUTION_URL', 'https://agent.sidbm.net/webhook-test')), '/'),
        'api_key' => (string) env('WA_GATEWAY_API_KEY', env('EVOLUTION_API_KEY', 'enpii:its.enpii-118')),
        'timeout' => (int) env('WA_GATEWAY_TIMEOUT', 15),
        'instance_prefix' => (string) env('WA_GATEWAY_INSTANCE_PREFIX', 'app-sidbm'),
    ],
];

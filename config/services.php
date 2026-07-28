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
];

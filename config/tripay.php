<?php

declare(strict_types=1);

use App\Services\PlatformSettingService;

return [
    'merchant_code' => app(PlatformSettingService::class)->get('tripay.merchant_code') ?? env('TRIPAY_MERCHANT_CODE', ''),
    'api_key' => app(PlatformSettingService::class)->getEncrypted('tripay.api_key') ?? env('TRIPAY_API_KEY', ''),
    'private_key' => app(PlatformSettingService::class)->getEncrypted('tripay.private_key') ?? env('TRIPAY_PRIVATE_KEY', ''),
    'mode' => app(PlatformSettingService::class)->get('tripay.mode') ?? env('TRIPAY_MODE', 'sandbox'),
    'default_method' => app(PlatformSettingService::class)->get('tripay.default_method') ?? env('TRIPAY_DEFAULT_METHOD', 'QRIS2'),
    'callback_url' => env('TRIPAY_CALLBACK_URL'),
];

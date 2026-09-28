<?php

declare(strict_types=1);

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for user supplied uploads (profile photos, tenant logos,
    | website assets and signature images). Defaults to the application disk
    | so switching FILESYSTEM_DISK=enstorage automatically routes uploads to
    | the EnStorage S3 cloud. Override explicitly to keep uploads local while
    | the default disk is used for other concerns.
    |
    */
    'upload_disk' => env('FILESYSTEM_UPLOAD_DISK', env('FILESYSTEM_DISK') === 'enstorage' ? 'enstorage' : 'public'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL', 'http://localhost').'/storage',
            'visibility' => 'public',
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],
        'enstorage' => [
            'driver' => 's3',
            'key' => env('ENSTORAGE_KEY', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('ENSTORAGE_SECRET', env('AWS_SECRET_ACCESS_KEY')),
            'region' => env('ENSTORAGE_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
            'bucket' => env('ENSTORAGE_BUCKET', env('AWS_BUCKET', 'public')),
            'url' => env('ENSTORAGE_URL', env('AWS_URL')),
            'endpoint' => env('ENSTORAGE_ENDPOINT', env('AWS_ENDPOINT', 'https://enstorage.enpiistudio.com/api/v1/s3')),
            'use_path_style_endpoint' => env('ENSTORAGE_USE_PATH_STYLE_ENDPOINT', true),
            /*
             | API key ikut dikirim sebagai header `X-API-Key` pada setiap
             | request. Signature SigV4 tetap dikirim, tetapi API key
             | menjamin autentikasi tetap berhasil apa pun perlakuan proxy
             | terhadap header yang ikut ditandatangani.
             |
             | Kunci `http` (bukan `options`) yang benar: FilesystemManager
             | meneruskan seluruh konfigurasi disk ke konstruktor S3Client,
             | dan hanya `http` yang dibaca Guzzle untuk opsi koneksi.
             */
            'http' => [
                'headers' => [
                    'X-API-Key' => env('ENSTORAGE_KEY', env('AWS_ACCESS_KEY_ID')),
                ],
            ],
            'throw' => false,
            'report' => false,
        ],
    ],
    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];

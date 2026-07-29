<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$t = App\Models\Platform\Tenant::query()->where('code', 'local')->first();
echo json_encode($t?->only(['row_id', 'code', 'name', 'district_code', 'status']), JSON_PRETTY_PRINT).PHP_EOL;

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): Builder
    {
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->boolean('ai_enabled')->nullable()->default(null)->after('is_training_mode');
        });
    }

    public function down(): void
    {
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->dropColumn('ai_enabled');
        });
    }
};

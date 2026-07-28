<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function schema(): \Illuminate\Database\Schema\Builder
    {
        return Schema::connection((string) config('tenancy.platform_connection', 'platform'));
    }

    public function up(): void
    {
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->string('district_code', 6)->nullable()->after('name');
            $table->unique('district_code', 'uq_tenants_district_code');
        });
    }

    public function down(): void
    {
        $this->schema()->table('tenants', function (Blueprint $table): void {
            $table->dropUnique('uq_tenants_district_code');
            $table->dropColumn('district_code');
        });
    }
};

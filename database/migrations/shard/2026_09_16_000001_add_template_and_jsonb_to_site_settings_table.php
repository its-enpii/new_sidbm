<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config('tenancy.tenant_connection', 'tenant');
    }

    public function up(): void
    {
        Schema::connection($this->connectionName())->table('site_settings', function (Blueprint $table): void {
            // Template chosen in the website builder. `after()` is honoured on
            // MySQL and ignored by the SQLite grammar used in tests.
            $table->string('template', 50)->default('classic')->after('tenant_id')
                ->comment('Builder template key: classic | modern | minimal.');
            // Section visibility + per-section copy (hero CTA, about, posts, officers, contact).
            $table->json('sections_config')->nullable()->after('footer_note');
            // Ordered list of organization officers rendered in the pengurus section.
            $table->json('officers_data')->nullable()->after('sections_config');
            // Draf vs Publik for the tenant's public site.
            $table->boolean('is_published')->default(true)->after('officers_data');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connectionName())->table('site_settings', function (Blueprint $table): void {
            $table->dropColumn(['template', 'sections_config', 'officers_data', 'is_published']);
        });
    }
};

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
        $schema = $this->schema();

        if ($schema->hasTable('users') && ! $schema->hasColumn('users', 'google_id')) {
            $schema->table('users', function (Blueprint $table): void {
                $table->string('google_id', 100)->nullable()->unique()->after('remember_token');
                $table->string('google_email', 190)->nullable()->after('google_id');
                $table->string('google_avatar', 255)->nullable()->after('google_email');
                $table->dateTime('google_linked_at')->nullable()->after('google_avatar');
                $table->json('email_notifications')->nullable()->after('google_linked_at');
            });
        }
    }

    public function down(): void
    {
        $schema = $this->schema();

        if ($schema->hasTable('users') && $schema->hasColumn('users', 'google_id')) {
            $schema->table('users', function (Blueprint $table): void {
                $table->dropColumn([
                    'google_id',
                    'google_email',
                    'google_avatar',
                    'google_linked_at',
                    'email_notifications',
                ]);
            });
        }
    }
};

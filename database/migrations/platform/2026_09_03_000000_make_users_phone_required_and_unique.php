<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'uq_users_phone';

    public function up(): void
    {
        $connection = (string) config('tenancy.platform_connection', 'platform');
        $schema = Schema::connection($connection);

        if (! $schema->hasColumn('users', 'phone') || $schema->hasIndex('users', self::INDEX_NAME)) {
            return;
        }

        $existingPhones = $schema->getConnection()->table('users')
            ->selectRaw('phone, count(*) as total')
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->groupBy('phone')
            ->havingRaw('count(*) > 1')
            ->pluck('total', 'phone');

        if ($existingPhones->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot require users.phone: duplicate WhatsApp phone values exist. Resolve rows before migrating: %s',
                $existingPhones->keys()->implode(', '),
            ));
        }

        $nullPhoneCount = $schema->getConnection()->table('users')
            ->whereNull('phone')
            ->orWhere('phone', '')
            ->count();

        if ($nullPhoneCount > 0) {
            throw new RuntimeException(sprintf(
                'Cannot require users.phone: %d user(s) have no phone. Backfill a valid WhatsApp number for every user before migrating.',
                $nullPhoneCount,
            ));
        }

        if (Schema::connection($connection)->getConnection()->getDriverName() === 'sqlite') {
            Schema::connection($connection)->table('users', function (Blueprint $table): void {
                $table->unique('phone', self::INDEX_NAME);
            });

            return;
        }

        Schema::connection($connection)->table('users', function (Blueprint $table): void {
            $table->string('phone', 20)->nullable(false)->unique(self::INDEX_NAME)->change();
        });
    }

    public function down(): void
    {
        $schema = Schema::connection((string) config('tenancy.platform_connection', 'platform'));

        if (! $schema->hasIndex('users', self::INDEX_NAME)) {
            return;
        }

        $schema->table('users', function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });

        if ($schema->getConnection()->getDriverName() !== 'sqlite') {
            $schema->table('users', function (Blueprint $table): void {
                $table->string('phone', 20)->nullable()->change();
            });
        }
    }
};

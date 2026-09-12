<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class HoldingSsoTest extends TestCase
{
    protected $connectionsToTransact = ['platform', 'tenant'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        config(['services.holding_sso.secret' => null]);
        Cache::flush();

        $this->prepareSqliteDatabase('platform');
        $this->prepareSqliteDatabase('tenant');

        Artisan::call('migrate', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
    }

    private function prepareSqliteDatabase(string $connection): void
    {
        if (DB::connection($connection)->getDriverName() !== 'sqlite') {
            return;
        }

        DB::connection($connection)->disconnect();
        $database = (string) config("database.connections.{$connection}.database");

        if (is_file($database)) {
            unlink($database);
        }

        touch($database);
        RefreshDatabaseState::$migrated = false;
    }

    public function test_valid_token_creates_tenant_user_and_is_consumed_once(): void
    {
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', $payload['email'])->firstOrFail();
        $this->assertSame($tenant->row_id, $user->tenant_id);
        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
        ]);
        $this->assertTrue(Auth::check());
        $this->assertNull(Cache::get('sso:'.hash('sha256', $token)));
    }

    public function test_second_use_redirects_to_login_without_session(): void
    {
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)->assertRedirect(route('dashboard'));
        Auth::forgetGuards();
        $this->flushSession();

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_expired_token_is_rejected(): void
    {
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant, ['exp' => now()->subMinute()->timestamp]);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.holding_sso.secret' => 'shared-secret']);
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant, ['signature' => 'invalid-signature']);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signature_is_required_when_secret_is_configured(): void
    {
        config(['services.holding_sso.secret' => 'shared-secret']);
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_unknown_sub_tenant_code_creates_no_user(): void
    {
        $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload(null, ['sub_tenant_code' => 'missing']);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Tenant tujuan tidak ditemukan.');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_holding_superadmin_role_never_becomes_local_superadmin(): void
    {
        $tenant = $this->createTenant();
        $token = Str::random(64);
        $payload = $this->payload($tenant, ['role' => 'superadmin']);
        Cache::put('sso:'.hash('sha256', $token), $payload, 60);

        $this->get('/auth/holding?token='.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(?Tenant $tenant, array $overrides = []): array
    {
        return [
            'tenant_application_id' => 12,
            'user_id' => 34,
            'email' => 'holding-owner@example.test',
            'name' => 'Holding Owner',
            'role' => 'tenant_owner',
            'tenant_name' => 'BUMDesma Contoh',
            'sub_tenant_code' => $tenant?->code,
            'exp' => now()->addMinute()->timestamp,
            ...$overrides,
        ];
    }

    private function createTenant(): Tenant
    {
        $tenantDatabase = (string) config('database.connections.tenant.database');
        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => $tenantDatabase,
            'credential_reference' => str_ends_with($tenantDatabase, '_test') ? 'test' : 'local',
            'placement_type' => 'shared',
            'status' => 'active',
        ]);

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Tenant',
            'status' => 'active',
            'timezone' => 'Asia/Jakarta',
            'metadata' => ['domains' => ['localhost']],
        ]);

        TenantPlacement::query()->create([
            'tenant_id' => $tenant->row_id,
            'shard_id' => $shard->row_id,
            'status' => 'active',
            'placed_at' => now(),
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        return $tenant;
    }
}

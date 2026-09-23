<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end verification of the "Ingat sesi saya" (Remember Me) feature on /login.
 *
 * The recaller cookie is inspected on the raw HTTP response (encrypted or not)
 * and replayed as an unencrypted cookie on a brand new request to prove the
 * user is re-authenticated from the remember cookie alone.
 */
final class RememberMeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);
    }

    public function test_login_with_remember_me_stores_remember_token_and_queues_cookie(): void
    {
        $user = $this->createTenantMember(['password' => 'secret-password']);
        $this->assertNull($user->fresh()->remember_token, 'remember_token should start null.');

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
            'remember' => true,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $this->assertNotNull(
            $user->fresh()->remember_token,
            'A remember_token must be persisted when remember-me is requested.'
        );

        $recallerName = Auth::guard()->getRecallerName();
        $response->assertCookie($recallerName);
    }

    public function test_login_without_remember_me_does_not_queue_remember_cookie(): void
    {
        $user = $this->createTenantMember(['password' => 'secret-password']);

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
            'remember' => false,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);

        $recallerName = Auth::guard()->getRecallerName();
        $response->assertCookieMissing($recallerName);
    }

    public function test_user_is_authenticated_via_remember_cookie_when_session_expires(): void
    {
        $user = $this->createTenantMember(['password' => 'secret-password']);

        $response = $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
            'remember' => true,
        ]);

        $response->assertRedirect('/dashboard');

        $recallerName = Auth::guard()->getRecallerName();

        // Raw (still encrypted) cookie value, exactly what a real browser stores/sends.
        $rawCookie = $response->getCookie($recallerName, false);
        $this->assertNotNull($rawCookie, 'The remember recaller cookie should be queued on login.');
        $this->assertStringContainsString(
            (string) $user->fresh()->remember_token,
            (string) $response->getCookie($recallerName)?->getValue(),
            'The recaller cookie must carry the persisted remember_token.'
        );

        // Simulate a brand new browser session: drop the PHPUnit session + guards,
        // then replay ONLY the recaller cookie that a real browser would still hold.
        $this->flushSession();
        Auth::forgetGuards();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $this->withUnencryptedCookie($recallerName, $rawCookie->getValue())
            ->get('/dashboard');

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_clears_remember_cookie_and_rotates_token(): void
    {
        $user = $this->createTenantMember(['password' => 'secret-password']);

        $this->post('/login', [
            'identifier' => $user->username,
            'password' => 'secret-password',
            'remember' => true,
        ])->assertRedirect('/dashboard');

        $oldToken = $user->fresh()->remember_token;
        $this->assertNotNull($oldToken);

        $response = $this->post('/logout');

        $response->assertRedirect('/login');
        $this->assertGuest();

        $newToken = $user->fresh()->remember_token;
        $this->assertNotSame(
            $oldToken,
            $newToken,
            'Logging out must rotate/reset the stored remember_token.'
        );

        $recallerName = Auth::guard()->getRecallerName();
        $rawLogoutCookie = $response->getCookie($recallerName, false);
        $this->assertTrue(
            $rawLogoutCookie === null || $rawLogoutCookie->getExpiresTime() < time(),
            'The recaller cookie must be expired or forgotten on logout.'
        );
    }

    private function createTenantMember(array $attributes = []): User
    {
        $tenantDb = (string) config('database.connections.tenant.database');

        $shard = DatabaseShard::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'local',
            'name' => 'Local Shard',
            'driver' => (string) config('database.connections.tenant.driver', 'mysql'),
            'host' => (string) config('database.connections.tenant.host', '127.0.0.1'),
            'port' => (int) config('database.connections.tenant.port', 3306),
            'database_name' => $tenantDb,
            'credential_reference' => str_ends_with($tenantDb, '_test') ? 'test' : 'local',
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

        $user = User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenant->row_id,
            'name' => 'Remember Me User',
            'email' => 'remember_me@example.test',
            'username' => 'remember_me_user',
            'password' => 'secret-password',
            'status' => 'active',
        ], $attributes));

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        Artisan::call('migrate:fresh', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/shard',
            '--force' => true,
        ]);
        Artisan::call('tenancy:sync-registry', ['--shard' => 'local']);

        config(['tenancy.local_tenant' => 'local']);

        return $user;
    }
}

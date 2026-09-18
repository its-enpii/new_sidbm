<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Platform\DatabaseShard;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\Platform\TenantPlacement;
use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

final class GoogleAuthTest extends TestCase
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

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-client-secret');
        config()->set('services.google.redirect_uri', 'http://localhost/auth/google/callback');
    }

    public function test_redirect_builds_google_authorization_url(): void
    {
        $this->get('/auth/google/redirect?action=login')
            ->assertRedirect();

        $target = $this->get('/auth/google/redirect?action=login')->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth', $target);
        $this->assertStringContainsString('client_id=test-client-id', $target);
        $this->assertStringContainsString('response_type=code', $target);
        $this->assertStringContainsString('scope=openid+profile+email', $target);
        $this->assertStringContainsString('prompt=select_account', $target);
        $this->assertStringContainsString('state=', $target);
    }

    public function test_redirect_rejects_linking_when_not_configured(): void
    {
        config()->set('services.google.client_id', null);

        $this->get('/auth/google/redirect?action=login')
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Integrasi Google belum dikonfigurasi.');
    }

    public function test_linking_requires_an_authenticated_session(): void
    {
        $this->get('/auth/google/redirect?action=link')
            ->assertRedirect('/login');
    }

    public function test_callback_logs_in_user_with_existing_google_id(): void
    {
        $user = $this->createUser([
            'google_id' => 'google-123',
            'google_email' => 'linked@example.test',
            'google_linked_at' => now(),
        ]);

        $this->fakeGoogleTokenAndUserinfo('google-123', 'linked@example.test');

        $this->get($this->callbackUrl('login'))
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($user);
        $this->assertEquals('google-123', $user->fresh()->google_id);
    }

    public function test_callback_rejects_unlinked_user_even_if_email_matches(): void
    {
        $user = $this->createUser([
            'email' => 'existing@example.test',
            'google_id' => null,
        ]);

        $this->fakeGoogleTokenAndUserinfo('google-999', 'existing@example.test');

        $this->get($this->callbackUrl('login'))
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_callback_does_not_auto_register_new_user(): void
    {
        $count = User::count();

        $this->fakeGoogleTokenAndUserinfo('google-new', 'brandnew@example.test');

        $this->get($this->callbackUrl('login'))
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertSame($count, User::count());
    }

    public function test_callback_rejects_unknown_google_account(): void
    {
        $this->fakeGoogleTokenAndUserinfo('google-unknown', 'nobody@example.test');

        $this->get($this->callbackUrl('login'))
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_callback_rejects_disabled_user(): void
    {
        $this->createUser([
            'email' => 'disabled@example.test',
            'google_id' => 'google-456',
            'status' => 'suspended',
        ]);

        $this->fakeGoogleTokenAndUserinfo('google-456', 'disabled@example.test');

        $this->get($this->callbackUrl('login'))
            ->assertRedirect('/login')
            ->assertSessionHas('error', 'Akun Anda dinonaktifkan.');

        $this->assertGuest();
    }

    public function test_callback_links_account_when_logged_in(): void
    {
        $user = $this->createUser(['email' => 'me@example.test']);
        $this->actingAs($user);

        $this->fakeGoogleTokenAndUserinfo('google-777', 'me@example.test');

        $this->get($this->callbackUrl('link'))
            ->assertRedirect('/profile?tab=account')
            ->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertEquals('google-777', $fresh->google_id);
        $this->assertEquals('me@example.test', $fresh->google_email);
        $this->assertNotNull($fresh->google_linked_at);
    }

    public function test_callback_refuses_google_id_owned_by_another_user(): void
    {
        $other = $this->createUser([
            'google_id' => 'google-taken',
            'google_email' => 'taken@example.test',
            'google_linked_at' => now(),
        ]);
        $user = $this->createUser(['email' => 'mine@example.test']);
        $this->actingAs($user);

        $this->fakeGoogleTokenAndUserinfo('google-taken', 'taken@example.test');

        $this->get($this->callbackUrl('link'))
            ->assertRedirect('/profile?tab=account')
            ->assertSessionHas('error', 'Akun Google ini sudah terhubung dengan akun lain.');

        $this->assertEquals('google-taken', $other->fresh()->google_id);
        $this->assertNull($user->fresh()->google_id);
    }

    public function test_callback_fails_on_expired_or_tampered_state(): void
    {
        $this->fakeGoogleTokenAndUserinfo('google-x', 'expired@example.test');

        $this->get('/auth/google/callback?code=fake-code&state=tampered-state')
            ->assertRedirect('/login')
            ->assertSessionHas('error');

        $this->assertGuest();
    }

    public function test_unlink_disconnects_google_account(): void
    {
        [$user] = $this->createUserWithTenant([
            'google_id' => 'google-unlink',
            'google_email' => 'unlink@example.test',
            'google_avatar' => 'https://example.test/a.png',
            'google_linked_at' => now(),
        ]);
        $this->actingAs($user);

        $this->delete('/profile/google')
            ->assertRedirect('/profile?tab=account')
            ->assertSessionHas('success', 'Hubungan akun Google berhasil diputuskan.');

        $fresh = $user->fresh();
        $this->assertNull($fresh->google_id);
        $this->assertNull($fresh->google_email);
        $this->assertNull($fresh->google_avatar);
        $this->assertNull($fresh->google_linked_at);
    }

    public function test_notification_preferences_can_be_updated(): void
    {
        [$user] = $this->createUserWithTenant();
        $this->actingAs($user);

        $this->put('/profile/notifications', ['billing' => false, 'announcements' => true])
            ->assertRedirect('/profile?tab=account')
            ->assertSessionHas('success', 'Preferensi notifikasi email berhasil diperbarui.');

        $fresh = $user->fresh();
        $this->assertFalse($fresh->receivesBillingEmail());
        $this->assertTrue($fresh->receivesAnnouncementEmail());
    }

    public function test_notification_preferences_default_to_opted_in(): void
    {
        $user = $this->createUser();

        $this->assertTrue($user->receivesBillingEmail());
        $this->assertTrue($user->receivesAnnouncementEmail());
        $this->assertEquals($user->email, $user->getNotificationEmail());
    }

    public function test_notification_email_prefers_google_email(): void
    {
        $user = $this->createUser([
            'email' => 'internal@example.test',
            'google_email' => 'google-account@example.test',
            'google_id' => 'google-pref',
            'google_linked_at' => now(),
        ]);

        $this->assertEquals('google-account@example.test', $user->getNotificationEmail());
        $this->assertTrue($user->isGoogleConnected());
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function createUserWithTenant(array $attributes = []): array
    {
        $shard = DatabaseShard::query()->firstOrCreate(
            ['code' => 'local'],
            [
                'public_id' => (string) Str::ulid(),
                'name' => 'Local Shard',
                'driver' => (string) config('database.connections.tenant.driver', 'sqlite'),
                'host' => 'sqlite',
                'port' => 3306,
                'database_name' => (string) config('database.connections.tenant.database'),
                'credential_reference' => 'local',
                'placement_type' => 'shared',
                'status' => 'active',
            ],
        );

        $tenant = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'google',
            'name' => 'Google Tenant',
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

        $user = $this->createUser(array_merge(['tenant_id' => $tenant->row_id], $attributes));

        TenantMembership::query()->create([
            'tenant_id' => $tenant->row_id,
            'user_id' => $user->row_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        config(['tenancy.local_tenant' => 'google']);

        return [$user, $tenant];
    }

    private function callbackUrl(string $action): string
    {
        $state = app(GoogleAuthService::class)->getAuthorizationUrl($action);
        parse_str(parse_url($state, PHP_URL_QUERY), $query);

        return '/auth/google/callback?code=fake-code&state='.urlencode($query['state']);
    }

    private function fakeGoogleTokenAndUserinfo(string $googleId, string $email): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-access-token',
                'token_type' => 'Bearer',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => $googleId,
                'email' => $email,
                'name' => 'Google User',
                'picture' => 'https://example.test/avatar.png',
            ], 200),
        ]);
    }

    private function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
            'public_id' => (string) Str::ulid(),
            'name' => 'Google User',
            'email' => Str::lower((string) Str::ulid()).'@example.test',
            'username' => 'g_'.Str::lower(Str::random(12)),
            'password' => 'password',
            'status' => 'active',
        ], $attributes));
    }
}

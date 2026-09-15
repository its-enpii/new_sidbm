<?php

declare(strict_types=1);

namespace Tests\Feature\Assistant;

use App\Domain\Access\Services\PermissionChecker;
use App\Models\User;
use App\Notifications\AiAccessRequestNotification;
use App\Services\PlatformSettingService;
use App\Tenancy\Middleware\ResolveTenant;
use Enpii\Assistant\AssistantServiceProvider;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class AssistantFeatureGateTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private User $tenantAdmin;

    private User $tenantStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->withoutMiddleware([ResolveTenant::class, PreventRequestForgery::class]);

        AssistantServiceProvider::$ragConnectionName = null;

        Artisan::call('migrate', [
            '--path' => 'vendor/enpii/assistant/database/migrations',
            '--force' => true,
        ]);

        $this->tenantAdmin = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Admin Tenant',
            'username' => 'admin_'.Str::lower(Str::random(6)),
            'email' => 'admin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $this->tenantStaff = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Staff Tenant',
            'username' => 'staff_'.Str::lower(Str::random(6)),
            'email' => 'staff_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        $checker = app(PermissionChecker::class);
        $checker->assignRole($this->tenantAdmin, 'admin');
        $checker->assignRole($this->tenantStaff, 'kasir');
    }

    public function test_global_ai_disabled_blocks_assistant_api_and_sets_shared_props(): void
    {
        // Force master killswitch OFF
        app(PlatformSettingService::class)->set('ai.enabled', false, 'bool');
        $this->testTenant->forceFill(['ai_enabled' => true])->save();

        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/assistant/persona');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'feature_disabled',
            'feature' => 'ai',
        ]);

        $pageResponse = $this->actingAs($this->tenantAdmin)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.gated', true)
        );
    }

    public function test_tenant_training_mode_with_null_ai_enabled_allows_ai(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => true,
            'ai_enabled' => null,
        ])->save();

        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/assistant/persona');

        $response->assertOk();

        $pageResponse = $this->actingAs($this->tenantAdmin)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', true)
            ->where('assistant.gated', false)
        );
    }

    public function test_tenant_non_training_with_null_ai_enabled_is_rejected_fail_closed(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => false,
            'ai_enabled' => null,
        ])->save();

        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/assistant/persona');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'feature_disabled',
            'feature' => 'ai',
            'request_url' => '/assistant/access-request',
        ]);

        $pageResponse = $this->actingAs($this->tenantAdmin)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.gated', true)
        );
    }

    public function test_tenant_non_training_with_ai_enabled_1_is_allowed(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => false,
            'ai_enabled' => true,
        ])->save();

        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/assistant/persona');

        $response->assertOk();

        $pageResponse = $this->actingAs($this->tenantAdmin)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', true)
            ->where('assistant.gated', false)
        );
    }

    public function test_tenant_training_mode_with_ai_enabled_0_is_rejected_override_wins(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => true,
            'ai_enabled' => false,
        ])->save();

        $response = $this->actingAs($this->tenantAdmin)
            ->getJson('/assistant/persona');

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'feature_disabled',
            'feature' => 'ai',
        ]);
    }

    public function test_user_without_assistant_use_permission_has_widget_disabled(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => true,
            'ai_enabled' => true,
        ])->save();

        $memberUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Anggota Biasa',
            'username' => 'anggota_'.Str::lower(Str::random(6)),
            'email' => 'anggota_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => false,
        ]);

        app(PermissionChecker::class)->assignRole($memberUser, 'anggota');

        $pageResponse = $this->actingAs($memberUser)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.gated', false)
        );
    }

    public function test_user_with_permission_when_ai_disabled_has_gated_true(): void
    {
        app(PlatformSettingService::class)->set('ai.enabled', true, 'bool');
        $this->testTenant->forceFill([
            'is_training_mode' => false,
            'ai_enabled' => false,
        ])->save();

        // Admin has assistant.use permission, but AI feature is gated off
        $pageResponse = $this->actingAs($this->tenantAdmin)->get('/dashboard');
        $pageResponse->assertInertia(fn (AssertableInertia $page) => $page
            ->where('assistant.enabled', false)
            ->where('assistant.gated', true)
        );
    }

    public function test_assistant_access_request_notifies_tenant_admin_and_rate_limits_daily(): void
    {
        Notification::fake();
        RateLimiter::clear('ai_access_req:'.$this->tenantStaff->row_id.':'.now()->format('Y-m-d'));

        $response = $this->actingAs($this->tenantStaff)->post('/assistant/access-request');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        Notification::assertSentTo(
            $this->tenantAdmin,
            AiAccessRequestNotification::class,
            function (AiAccessRequestNotification $notification): bool {
                return $notification->requester->row_id === $this->tenantStaff->row_id;
            }
        );

        // Second request on the same day must be rate limited with info flash
        $secondResponse = $this->actingAs($this->tenantStaff)->post('/assistant/access-request');
        $secondResponse->assertRedirect();
        $secondResponse->assertSessionHas('info');
    }
}

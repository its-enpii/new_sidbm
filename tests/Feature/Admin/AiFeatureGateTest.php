<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class AiFeatureGateTest extends TestCase
{
    private Tenant $tenant1;

    private Tenant $tenant2;

    private Tenant $tenant3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->tenant1 = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tnt-001',
            'name' => 'Tenant Satu',
            'status' => 'active',
            'is_training_mode' => false,
        ]);

        $this->tenant2 = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tnt-002',
            'name' => 'Tenant Dua',
            'status' => 'active',
            'is_training_mode' => true,
        ]);

        $this->tenant3 = Tenant::query()->create([
            'public_id' => (string) Str::ulid(),
            'code' => 'tnt-003',
            'name' => 'Tenant Tiga',
            'status' => 'active',
            'is_training_mode' => false,
        ]);
    }

    public function test_non_superadmin_cannot_access_features_page(): void
    {
        $tenantUser = $this->createUser(false, $this->tenant1->row_id);

        $this->actingAs($tenantUser)
            ->get('/admin/features')
            ->assertRedirect();
    }

    public function test_superadmin_can_view_features_page(): void
    {
        $superadmin = $this->createUser(true);

        $response = $this->actingAs($superadmin)->get('/admin/features');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Features/Index')
            ->has('tenants.data', 3)
            ->has('globalAiEnabled')
            ->where('globalAiEnabled', true)
        );
    }

    public function test_superadmin_can_update_global_ai_killswitch(): void
    {
        $superadmin = $this->createUser(true);

        $response = $this->actingAs($superadmin)->patch('/admin/features/ai/global', [
            'enabled' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $settings = app(PlatformSettingService::class);
        $settings->flush();
        self::assertFalse($settings->get('ai.enabled'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'platform_setting.ai.update',
        ], 'platform');
    }

    public function test_superadmin_can_update_single_tenant_ai_override(): void
    {
        $superadmin = $this->createUser(true);

        // Turn on for tenant1
        $response = $this->actingAs($superadmin)->patch("/admin/features/ai/{$this->tenant1->row_id}", [
            'value' => 'on',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->tenant1->refresh();
        self::assertTrue($this->tenant1->ai_enabled);
        self::assertTrue($this->tenant1->aiEnabledExplicit());

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.ai_feature.update',
            'subject_id' => $this->tenant1->row_id,
        ], 'platform');

        // Turn off
        $this->actingAs($superadmin)->patch("/admin/features/ai/{$this->tenant1->row_id}", [
            'value' => 'off',
        ]);
        $this->tenant1->refresh();
        self::assertFalse($this->tenant1->ai_enabled);
        self::assertFalse($this->tenant1->aiEnabledExplicit());

        // Set inherit (null)
        $this->actingAs($superadmin)->patch("/admin/features/ai/{$this->tenant1->row_id}", [
            'value' => 'inherit',
        ]);
        $this->tenant1->refresh();
        self::assertNull($this->tenant1->ai_enabled);
        self::assertNull($this->tenant1->aiEnabledExplicit());
    }

    public function test_superadmin_can_bulk_update_tenant_ai_status(): void
    {
        $superadmin = $this->createUser(true);

        $response = $this->actingAs($superadmin)->post('/admin/features/ai/bulk', [
            'tenant_ids' => [$this->tenant1->row_id, $this->tenant3->row_id],
            'value' => 'on',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->tenant1->refresh();
        $this->tenant2->refresh();
        $this->tenant3->refresh();

        self::assertTrue($this->tenant1->ai_enabled);
        self::assertNull($this->tenant2->ai_enabled); // untouched
        self::assertTrue($this->tenant3->ai_enabled);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant.ai_feature.bulk',
        ], 'platform');
    }

    public function test_tenant_edit_form_updates_ai_enabled_state(): void
    {
        $superadmin = $this->createUser(true);

        $response = $this->actingAs($superadmin)->put("/admin/tenants/{$this->tenant1->row_id}", [
            'name' => 'Tenant Satu Updated',
            'status' => 'active',
            'ai_enabled' => 'on',
        ]);

        $response->assertRedirect();
        $this->tenant1->refresh();
        self::assertSame('Tenant Satu Updated', $this->tenant1->name);
        self::assertTrue($this->tenant1->ai_enabled);
    }

    private function createUser(bool $isSuperadmin, ?int $tenantId = null): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $tenantId,
            'name' => $isSuperadmin ? 'Super Admin' : 'Tenant User',
            'username' => 'user_'.Str::lower(Str::random(6)),
            'email' => 'user_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => $isSuperadmin,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionCatalogService;
use App\Domain\Access\Services\PermissionChecker;
use App\Models\User;
use App\Services\Admin\TenantUserService;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class TenantRoleManagementTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private PermissionCatalogService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
        $this->rebuildTenantTestDatabases();
        $this->catalog = app(PermissionCatalogService::class);
        $this->workbenchRun(fn () => app(PermissionChecker::class)->ensureSystemRoles());
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_superadmin_can_view_tenant_roles_list(): void
    {
        $this->clearTenantTestContext();

        $response = $this->actingAs($this->superadmin())
            ->get("/admin/tenants/{$this->testTenant->row_id}/roles");

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Admin/Tenants/Roles/Index')
            ->where('tenant.row_id', $this->testTenant->row_id)
            ->where('roles', fn ($roles) => collect($roles)->contains(
                fn ($role) => $role['code'] === 'admin'
                    && $role['is_locked'] === true
                    && $role['is_system'] === true
            ))
            ->etc());
    }

    public function test_tenant_roles_list_can_be_filtered_by_search(): void
    {
        $this->clearTenantTestContext();
        $this->createShardRole('pustakawan', 'Pustakawan Desa', ['members.view']);

        $this->actingAs($this->superadmin())
            ->get("/admin/tenants/{$this->testTenant->row_id}/roles?search=pustaka")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Tenants/Roles/Index')
                ->has('roles', 1)
                ->where('roles.0.code', 'pustakawan')
                ->etc());
    }

    public function test_superadmin_can_create_custom_role_for_tenant(): void
    {
        $this->clearTenantTestContext();

        $response = $this->actingAs($this->superadmin())->post("/admin/tenants/{$this->testTenant->row_id}/roles", [
            'name' => 'Petugas Lapangan',
            'code' => 'petugas-lapangan',
            'description' => 'Input proposal desa',
            'permissions' => ['members.view', 'loans.view', 'loans.propose'],
        ]);

        $response->assertRedirect("/admin/tenants/{$this->testTenant->row_id}/roles");

        $role = $this->roleInShard('petugas_lapangan');
        $this->assertNotNull($role);
        $this->assertSame('Petugas Lapangan', $role->name);
        $this->assertFalse((bool) $role->is_system);
        $this->assertSame(['members.view', 'loans.view', 'loans.propose'], $role->permissions);
    }

    public function test_superadmin_cannot_reuse_protected_or_existing_role_code(): void
    {
        $this->clearTenantTestContext();
        $admin = $this->superadmin();

        $this->actingAs($admin)->post("/admin/tenants/{$this->testTenant->row_id}/roles", [
            'name' => 'Admin Palsu',
            'code' => 'admin',
            'permissions' => ['members.view'],
        ])->assertSessionHasErrors('code');

        $this->assertNull($this->roleInShard('admin_palsu'));

        $this->actingAs($admin)->post("/admin/tenants/{$this->testTenant->row_id}/roles", [
            'name' => 'Kasir Dua',
            'code' => 'kasir',
            'permissions' => ['members.view'],
        ])->assertSessionHasErrors('code');
    }

    public function test_superadmin_can_update_tenant_role_permissions(): void
    {
        $this->clearTenantTestContext();

        $role = $this->createShardRole('bendahara', 'Bendahara', ['members.view']);

        $response = $this->actingAs($this->superadmin())->put(
            "/admin/tenants/{$this->testTenant->row_id}/roles/{$role->row_id}",
            [
                'name' => 'Bendahara Senior',
                'description' => 'Kelola kas',
                'permissions' => ['members.view', 'journals.create', 'bukan.izin.asli'],
            ],
        );

        $response->assertRedirect("/admin/tenants/{$this->testTenant->row_id}/roles");

        $fresh = $this->roleInShard('bendahara');
        $this->assertSame('Bendahara Senior', $fresh->name);
        $this->assertSame(['members.view', 'journals.create'], $fresh->permissions);
    }

    public function test_admin_role_permission_matrix_is_locked(): void
    {
        $this->clearTenantTestContext();

        $adminRole = $this->roleInShard('admin');
        $this->assertNotNull($adminRole);

        $this->actingAs($this->superadmin())->put(
            "/admin/tenants/{$this->testTenant->row_id}/roles/{$adminRole->row_id}",
            ['name' => 'Diubah', 'permissions' => ['members.view']],
        )->assertRedirect();

        $this->assertNotSame('Diubah', $this->roleInShard('admin')->name);
    }

    public function test_superadmin_cannot_delete_system_role_or_in_use_role(): void
    {
        $this->clearTenantTestContext();
        $admin = $this->superadmin();

        $systemRole = $this->roleInShard('kasir');
        $this->assertNotNull($systemRole);

        $this->actingAs($admin)
            ->delete("/admin/tenants/{$this->testTenant->row_id}/roles/{$systemRole->row_id}")
            ->assertSessionHas('error');
        $this->assertNotNull($this->roleInShard('kasir'));

        $customRole = $this->createShardRole('pustakawan', 'Pustakawan', ['members.view']);
        $user = $this->tenantUser();
        $this->workbenchRun(fn () => UserRole::query()->create([
            'platform_user_id' => (int) $user->row_id,
            'role_row_id' => (int) $customRole->row_id,
        ]));

        $this->actingAs($admin)
            ->delete("/admin/tenants/{$this->testTenant->row_id}/roles/{$customRole->row_id}")
            ->assertSessionHas('error');
        $this->assertNotNull($this->roleInShard('pustakawan'));
    }

    public function test_superadmin_can_delete_unused_custom_role(): void
    {
        $this->clearTenantTestContext();

        $customRole = $this->createShardRole('pustakawan', 'Pustakawan', ['members.view']);

        $this->actingAs($this->superadmin())
            ->delete("/admin/tenants/{$this->testTenant->row_id}/roles/{$customRole->row_id}")
            ->assertRedirect("/admin/tenants/{$this->testTenant->row_id}/roles")
            ->assertSessionHas('success');

        $this->assertNull($this->roleInShard('pustakawan'));
    }

    public function test_superadmin_can_assign_custom_role_to_tenant_user(): void
    {
        $this->clearTenantTestContext();
        $admin = $this->superadmin();

        $role = $this->createShardRole('staf_sumber_ajar', 'Staf Sumber Belajar', ['members.view', 'reports.view']);

        $user = $this->tenantUser();

        $this->actingAs($admin)->get("/admin/tenants/{$this->testTenant->row_id}/users/{$user->row_id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Tenants/Users/Form')
                ->where('roleOptions', fn ($options) => collect($options)->contains(
                    fn ($option) => $option['value'] === 'staf_sumber_ajar' && str_contains($option['label'], '(Kustom)')
                )));

        $this->actingAs($admin)->put("/admin/tenants/{$this->testTenant->row_id}/users/{$user->row_id}", [
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => 'active',
            'role' => $role->code,
        ])->assertRedirect();

        $this->assertSame(
            ['staf_sumber_ajar'],
            app(TenantUserService::class)->rolesFor($this->testTenant, $user->fresh()),
        );
        $effective = $this->workbenchRun(
            fn () => app(PermissionChecker::class)->listFor($user->fresh()),
        );
        $this->assertSame(['members.view', 'reports.view'], $effective);
    }

    public function test_non_superadmin_cannot_access_tenant_roles_management(): void
    {
        $this->clearTenantTestContext();

        $user = $this->tenantUser();

        $this->actingAs($user)
            ->get("/admin/tenants/{$this->testTenant->row_id}/roles")
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/admin/tenants/{$this->testTenant->row_id}/roles", [
                'name' => 'Role Ilegal',
                'code' => 'role_ilegal',
                'permissions' => ['members.manage'],
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertNull($this->roleInShard('role_ilegal'));
    }

    private function superadmin(): User
    {
        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Super Admin',
            'username' => 'superadmin_'.Str::lower(Str::random(6)),
            'email' => 'superadmin_'.Str::lower(Str::random(6)).'@example.test',
            'password' => 'password',
            'status' => 'active',
            'is_superadmin' => true,
        ]);
    }

    private function tenantUser(): User
    {
        $suffix = Str::lower(Str::random(6));

        return User::query()->create([
            'public_id' => (string) Str::ulid(),
            'tenant_id' => $this->testTenant->row_id,
            'name' => 'Staf Tenant',
            'username' => 'staf_'.$suffix,
            'email' => "staf_{$suffix}@example.test",
            'phone' => '0813'.str_repeat((string) random_int(10000000, 99999999), 1),
            'password' => 'password',
            'status' => 'active',
        ]);
    }

    private function roleInShard(string $code): ?Role
    {
        return $this->workbenchRun(fn () => Role::query()
            ->where('tenant_id', (int) $this->testTenant->row_id)
            ->where('code', $code)
            ->first());
    }

    private function createShardRole(string $code, string $name, array $permissions): Role
    {
        return $this->workbenchRun(fn () => Role::query()->create([
            'tenant_id' => (int) $this->testTenant->row_id,
            'code' => $code,
            'name' => $name,
            'is_system' => false,
            'permissions' => $permissions,
        ]));
    }

    private function workbenchRun(callable $callback): mixed
    {
        return app(TenantWorkbench::class)->run($this->testTenant, $callback);
    }
}

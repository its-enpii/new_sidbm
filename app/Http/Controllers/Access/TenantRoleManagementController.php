<?php

declare(strict_types=1);

namespace App\Http\Controllers\Access;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionCatalogService;
use App\Domain\Access\Services\PermissionChecker;
use App\Http\Requests\Access\TenantRoleRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class TenantRoleManagementController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PermissionChecker $permissions,
        private readonly PermissionCatalogService $catalog,
    ) {}

    public function index(): Response
    {
        $this->permissions->ensureSystemRoles();

        $excludedCodes = ['regency_supervisor', 'province_supervisor'];

        $roles = Role::query()
            ->whereNotIn('code', $excludedCodes)
            ->withCount('userRoles')
            ->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();

        $rolesPayload = $roles->map(fn (Role $role): array => [
            'row_id' => $role->row_id,
            'code' => $role->code,
            'name' => $role->name,
            'description' => $role->description,
            'is_system' => (bool) $role->is_system,
            'is_locked' => $role->code === 'admin',
            'user_count' => (int) $role->user_roles_count,
            'permissions_count' => $role->code === 'admin'
                ? count($this->allTenantPermissionsFlat())
                : count($this->resolveRolePermissions($role)),
        ]);

        return Inertia::render('Access/Roles/Index', [
            'roles' => $rolesPayload,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Access/Roles/Form', [
            'role' => null,
            'permissionGroups' => $this->groupedTenantPermissions(),
        ]);
    }

    public function store(TenantRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($this->catalog->isProtectedCode($data['code'])) {
            return back()->with('error', 'Kode role tersebut dilindungi dan tidak dapat digunakan.');
        }

        Role::query()->create([
            'name' => $data['name'],
            'code' => Str::slug($data['code'], '_'),
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'permissions' => $data['permissions'] ?? [],
        ]);

        return to_route('access.roles.index')->with('success', 'Role kustom berhasil ditambahkan.');
    }

    public function edit(Role $role): Response
    {
        $this->assertBelongs($role);

        $isLocked = $role->code === 'admin';
        $activePermissions = $isLocked
            ? $this->allTenantPermissionsFlat()
            : $this->resolveRolePermissions($role);

        return Inertia::render('Access/Roles/Form', [
            'role' => [
                'row_id' => $role->row_id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'is_locked' => $isLocked,
                'permissions' => $activePermissions,
            ],
            'permissionGroups' => $this->groupedTenantPermissions(),
        ]);
    }

    public function update(TenantRoleRequest $request, Role $role): RedirectResponse
    {
        $this->assertBelongs($role);

        if ($role->code === 'admin') {
            return back()->with('error', 'Role Administrator dikunci dan tidak dapat diubah.');
        }

        $data = $request->validated();

        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $data['permissions'] ?? [],
        ];

        // Only allow code change for non-system roles
        if (! $role->is_system) {
            $updateData['code'] = Str::slug($data['code'], '_');
        }

        $role->update($updateData);

        return to_route('access.roles.index')->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->assertBelongs($role);

        if ($role->is_system || $role->code === 'admin') {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        $userCount = UserRole::query()->where('role_row_id', (int) $role->row_id)->count();
        if ($userCount > 0) {
            return back()->with('error', "Role ini sedang digunakan oleh {$userCount} pengguna dan tidak dapat dihapus.");
        }

        $role->delete();

        return to_route('access.roles.index')->with('success', 'Role berhasil dihapus.');
    }

    private function resolveRolePermissions(Role $role): array
    {
        if (is_array($role->permissions)) {
            return $role->permissions;
        }

        $packs = config('permissions.roles', []);

        return $packs[$role->code]['permissions'] ?? [];
    }

    /** @return list<string> */
    private function allTenantPermissionsFlat(): array
    {
        return $this->catalog->flat();
    }

    /** @return list<array{category: string, label: string, icon: string, permissions: list<array{key: string, label: string, description: string}>}> */
    private function groupedTenantPermissions(): array
    {
        return $this->catalog->groups();
    }

    private function assertBelongs(Role $role): void
    {
        abort_unless((int) $role->tenant_id === $this->tenantContext->id(), 404);
    }
}

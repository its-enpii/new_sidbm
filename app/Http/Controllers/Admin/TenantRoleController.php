<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Domain\Access\Services\PermissionCatalogService;
use App\Domain\Access\Services\PermissionChecker;
use App\Http\Requests\Admin\TenantRoleRequest;
use App\Models\Platform\Tenant;
use App\Services\Admin\AuditLogger;
use App\Tenancy\Services\TenantWorkbench;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Superadmin management of a tenant's roles + permission matrix, executed
 * directly against the tenant shard so no impersonation round-trip is needed.
 */
final class TenantRoleController
{
    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly PermissionCatalogService $catalog,
    ) {}

    public function index(Request $request, Tenant $tenant, TenantWorkbench $workbench): Response
    {
        $search = trim((string) $request->query('search', ''));
        $roles = $this->listRoles($tenant, $workbench, $search);

        return Inertia::render('Admin/Tenants/Roles/Index', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'roles' => $roles,
            'search' => $search,
        ]);
    }

    public function create(Tenant $tenant, TenantWorkbench $workbench): Response
    {
        return Inertia::render('Admin/Tenants/Roles/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'role' => null,
            'permissionGroups' => $this->catalog->groups(),
            'systemRoleCodes' => $this->systemRoleCodes($tenant, $workbench),
        ]);
    }

    public function store(TenantRoleRequest $request, Tenant $tenant, TenantWorkbench $workbench, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $code = Str::slug((string) ($data['code'] ?? ''), '_');

        if ($code === '') {
            return back()->withInput()->withErrors(['code' => 'Kode role wajib diisi.']);
        }

        if ($this->catalog->isProtectedCode($code)) {
            return back()->withInput()->withErrors(['code' => 'Kode role tersebut dilindungi dan tidak dapat digunakan.']);
        }

        if ($this->roleCodeExists($tenant, $workbench, $code)) {
            return back()->withInput()->withErrors(['code' => 'Kode role sudah dipakai pada tenant ini.']);
        }

        $permissions = $this->sanitizePermissions((array) ($data['permissions'] ?? []));

        $roleId = $workbench->run($tenant, function () use ($data, $code, $permissions): int {
            $role = Role::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'description' => $data['description'] ?? null,
                'is_system' => false,
                'permissions' => $permissions,
            ]);

            return (int) $role->row_id;
        });

        $audit->record(
            'tenant_role.create',
            $tenant,
            Role::class,
            $roleId,
            sprintf('Role [%s] dibuat pada tenant [%s].', $code, $tenant->code),
            ['permissions_count' => count($permissions)],
        );

        return to_route('admin.tenants.roles.index', $tenant)->with('success', 'Role kustom berhasil ditambahkan.');
    }

    public function edit(Tenant $tenant, int $roleId, TenantWorkbench $workbench): Response
    {
        $role = $this->findRole($tenant, $workbench, $roleId);

        $isLocked = $role->code === 'admin';
        $activePermissions = $isLocked ? $this->catalog->flat() : $this->resolvePermissions($role);

        return Inertia::render('Admin/Tenants/Roles/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'role' => [
                'row_id' => (int) $role->row_id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'is_locked' => $isLocked,
                'permissions' => $activePermissions,
            ],
            'permissionGroups' => $this->catalog->groups(),
            'systemRoleCodes' => $this->systemRoleCodes($tenant, $workbench),
        ]);
    }

    public function update(TenantRoleRequest $request, Tenant $tenant, int $roleId, TenantWorkbench $workbench, AuditLogger $audit): RedirectResponse
    {
        $role = $this->findRole($tenant, $workbench, $roleId);
        $data = $request->validated();

        if ($role->code === 'admin') {
            return back()->with('error', 'Role Administrator dikunci dan tidak dapat diubah.');
        }

        $permissions = $this->sanitizePermissions((array) ($data['permissions'] ?? []));
        $before = [
            'name' => $role->name,
            'description' => $role->description,
            'permissions' => $this->resolvePermissions($role),
        ];

        $workbench->run($tenant, function () use ($role, $data, $permissions): void {
            $role->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'permissions' => $permissions,
            ])->save();
        });

        $audit->record(
            'tenant_role.update',
            $tenant,
            Role::class,
            (int) $role->row_id,
            sprintf('Role [%s] diperbarui pada tenant [%s].', $role->code, $tenant->code),
            ['changes' => AuditLogger::diff($before, ['name' => $data['name'], 'description' => $data['description'] ?? null, 'permissions' => $permissions])],
        );

        return to_route('admin.tenants.roles.index', $tenant)->with('success', 'Role berhasil diperbarui.');
    }

    public function destroy(Tenant $tenant, int $roleId, TenantWorkbench $workbench, AuditLogger $audit): RedirectResponse
    {
        $role = $this->findRole($tenant, $workbench, $roleId);

        if ($role->is_system || $role->code === 'admin') {
            return back()->with('error', 'Role bawaan sistem tidak dapat dihapus.');
        }

        $inUse = $workbench->run($tenant, fn (): int => UserRole::query()->where('role_row_id', (int) $role->row_id)->count());

        if ($inUse > 0) {
            return back()->with('error', "Role ini sedang digunakan oleh {$inUse} pengguna dan tidak dapat dihapus.");
        }

        $code = (string) $role->code;
        $workbench->run($tenant, fn () => $role->delete());

        $audit->record(
            'tenant_role.delete',
            $tenant,
            Role::class,
            (int) $role->row_id,
            sprintf('Role [%s] dihapus dari tenant [%s].', $code, $tenant->code),
        );

        return to_route('admin.tenants.roles.index', $tenant)->with('success', 'Role berhasil dihapus.');
    }

    /** @return list<array<string, mixed>> */
    private function listRoles(Tenant $tenant, TenantWorkbench $workbench, string $search = ''): array
    {
        return $workbench->run($tenant, function () use ($tenant, $search): array {
            $this->permissions->ensureSystemRoles();

            $roles = Role::query()
                ->where('tenant_id', (int) $tenant->row_id)
                ->withCount('userRoles')
                ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")))
                ->orderByDesc('is_system')
                ->orderBy('name')
                ->get();

            $catalogSize = count($this->catalog->flat());

            return $roles->map(fn (Role $role): array => [
                'row_id' => (int) $role->row_id,
                'code' => (string) $role->code,
                'name' => (string) $role->name,
                'description' => $role->description,
                'is_system' => (bool) $role->is_system,
                'is_locked' => $role->code === 'admin',
                'user_count' => (int) $role->user_roles_count,
                'permissions_count' => $role->code === 'admin'
                    ? $catalogSize
                    : count($this->resolvePermissions($role)),
            ])->all();
        });
    }

    /** @return list<string> */
    private function systemRoleCodes(Tenant $tenant, TenantWorkbench $workbench): array
    {
        return $workbench->run($tenant, function () use ($tenant): array {
            $this->permissions->ensureSystemRoles();

            return Role::query()
                ->where('tenant_id', (int) $tenant->row_id)
                ->where('is_system', true)
                ->pluck('code')
                ->map(fn ($code): string => (string) $code)
                ->all();
        });
    }

    private function findRole(Tenant $tenant, TenantWorkbench $workbench, int $roleId): Role
    {
        $role = $workbench->run($tenant, fn (): ?Role => Role::query()
            ->where('tenant_id', (int) $tenant->row_id)
            ->whereKey($roleId)
            ->first());

        if ($role === null) {
            abort(404);
        }

        return $role;
    }

    private function roleCodeExists(Tenant $tenant, TenantWorkbench $workbench, string $code): bool
    {
        return $workbench->run($tenant, fn (): bool => Role::query()
            ->where('tenant_id', (int) $tenant->row_id)
            ->where('code', $code)
            ->exists());
    }

    /** @return list<string> */
    private function resolvePermissions(Role $role): array
    {
        if (is_array($role->permissions)) {
            return array_values(array_map('strval', $role->permissions));
        }

        $packs = config('permissions.roles', []);

        return array_values(array_map('strval', $packs[$role->code]['permissions'] ?? []));
    }

    /**
     * @param  array<array-key, mixed>  $permissions
     * @return list<string>
     */
    private function sanitizePermissions(array $permissions): array
    {
        $normalized = array_map(fn ($permission): string => trim((string) $permission), $permissions);

        return array_values(array_unique(array_intersect($normalized, $this->catalog->flat())));
    }
}

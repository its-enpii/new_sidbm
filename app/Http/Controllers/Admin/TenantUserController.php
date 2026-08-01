<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreTenantUserRequest;
use App\Http\Requests\Admin\UpdateTenantUserRequest;
use App\Models\Platform\Tenant;
use App\Models\User;
use App\Services\Admin\TenantUserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class TenantUserController
{
    public function index(Request $request, Tenant $tenant, TenantUserService $users): Response
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50, 100], true)
            ? (int) $request->query('per_page')
            : 15;

        $paginator = User::query()
            ->where('tenant_id', $tenant->row_id)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $roleMap = $users->rolesForMany(
            $tenant,
            $paginator->getCollection()->pluck('row_id')->map(fn ($id) => (int) $id)->all(),
        );

        $usersPayload = $paginator->through(fn (User $user): array => [
            'row_id' => $user->row_id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'status' => $user->status,
            'role' => $roleMap[(int) $user->row_id][0] ?? null,
            'roles' => $roleMap[(int) $user->row_id] ?? [],
            'last_login_at' => $user->last_login_at?->toDateTimeString(),
        ]);

        return Inertia::render('Admin/Tenants/Users/Index', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'users' => $usersPayload,
            'search' => $search,
            'perPage' => $perPage,
            'sort' => 'name',
            'direction' => 'asc',
        ]);
    }

    public function create(Tenant $tenant): Response
    {
        return Inertia::render('Admin/Tenants/Users/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'user' => null,
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function store(StoreTenantUserRequest $request, Tenant $tenant, TenantUserService $users): RedirectResponse
    {
        $users->create($tenant, $request->validated());

        return to_route('admin.tenants.users.index', $tenant)->with('success', 'Pengguna ditambahkan.');
    }

    public function edit(Tenant $tenant, User $user, TenantUserService $users): Response
    {
        $this->assertBelongs($tenant, $user);
        $roles = $users->rolesFor($tenant, $user);

        return Inertia::render('Admin/Tenants/Users/Form', [
            'tenant' => $tenant->only(['row_id', 'code', 'name']),
            'user' => [
                ...$user->only(['row_id', 'name', 'username', 'email', 'status']),
                'role' => $roles[0] ?? null,
            ],
            'roleOptions' => $this->roleOptions(),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, Tenant $tenant, User $user, TenantUserService $users): RedirectResponse
    {
        $this->assertBelongs($tenant, $user);
        $users->update($tenant, $user, $request->validated());

        return to_route('admin.tenants.users.index', $tenant)->with('success', 'Pengguna diperbarui.');
    }

    public function resetPassword(Request $request, Tenant $tenant, User $user, TenantUserService $users): RedirectResponse
    {
        $this->assertBelongs($tenant, $user);
        $data = $request->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        $users->resetPassword($user, $data['password']);

        return back()->with('success', 'Password direset.');
    }

    /** @return list<array{value: string, label: string}> */
    private function roleOptions(): array
    {
        $options = [
            ['value' => '', 'label' => 'Tanpa role (akses penuh legacy)'],
        ];
        foreach (config('permissions.roles', []) as $code => $def) {
            $options[] = [
                'value' => (string) $code,
                'label' => (string) ($def['name'] ?? $code),
            ];
        }

        return $options;
    }

    private function assertBelongs(Tenant $tenant, User $user): void
    {
        abort_unless((int) $user->tenant_id === (int) $tenant->row_id, 404);
    }
}

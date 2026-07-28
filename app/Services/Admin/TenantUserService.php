<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final readonly class TenantUserService
{
    public function create(Tenant $tenant, array $data): User
    {
        return DB::connection('platform')->transaction(function () use ($tenant, $data): User {
            $user = User::query()->create([
                'public_id' => (string) Str::ulid(),
                'tenant_id' => $tenant->row_id,
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'username' => $data['username'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? 'active',
            ]);

            TenantMembership::query()->create([
                'tenant_id' => $tenant->row_id,
                'user_id' => $user->row_id,
                'status' => ($data['status'] ?? 'active') === 'active' ? 'active' : 'suspended',
                'joined_at' => now(),
            ]);

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::connection('platform')->transaction(function () use ($user, $data): User {
            $user->forceFill([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'username' => $data['username'],
                'status' => $data['status'],
            ])->save();

            if (! empty($data['password'])) {
                $user->forceFill(['password' => Hash::make($data['password'])])->save();
            }

            TenantMembership::query()
                ->where('user_id', $user->row_id)
                ->where('tenant_id', $user->tenant_id)
                ->update([
                    'status' => $data['status'] === 'active' ? 'active' : 'suspended',
                ]);

            return $user->fresh();
        });
    }

    public function resetPassword(User $user, string $password): void
    {
        $user->forceFill(['password' => Hash::make($password)])->save();
    }
}

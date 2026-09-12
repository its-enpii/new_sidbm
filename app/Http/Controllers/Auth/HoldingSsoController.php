<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Models\Role;
use App\Domain\Access\Models\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Platform\Tenant;
use App\Models\Platform\TenantMembership;
use App\Models\User;
use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class HoldingSsoController extends Controller
{
    /**
     * Holding roles are intentionally mapped in one place. Holding tenant
     * owners become SIDBM tenant admins; staff have no implicit SIDBM access.
     */
    private const ROLE_MAP = [
        'tenant_owner' => 'admin',
        'tenant_staff' => null,
    ];

    public function __invoke(
        Request $request,
        TenantResolver $resolver,
        TenantContext $context,
        ShardConnectionManager $connections,
    ): RedirectResponse {
        $token = (string) $request->query('token', '');
        $payload = $token === '' ? null : Cache::pull('sso:'.hash('sha256', $token));

        if (! is_array($payload) || ! $this->isValidPayload($payload) || $this->expiresAt($payload) <= now()->timestamp) {
            return $this->redirectFailed($request);
        }

        if (! $this->hasValidSignature($payload)) {
            return $this->redirectFailed($request);
        }

        $subTenantCode = $payload['sub_tenant_code'];
        try {
            $tenant = is_string($subTenantCode) && $subTenantCode !== ''
                ? $resolver->resolveByCode($subTenantCode)
                : $this->defaultTenant();
        } catch (Throwable) {
            return $this->redirectFailed($request, 'Tenant tujuan tidak ditemukan.');
        }

        $role = self::ROLE_MAP[(string) $payload['role']] ?? null;
        if ($role === null) {
            return $this->redirectFailed($request, 'Peran SSO tidak dapat digunakan di aplikasi ini.');
        }

        $this->activateTenant($tenant, $context, $connections);

        try {
            $user = DB::connection('platform')->transaction(function () use ($payload, $tenant): User {
                $values = [
                    'name' => (string) $payload['name'],
                    'password' => Hash::make(bin2hex(random_bytes(20))),
                    'status' => 'active',
                    'tenant_id' => $tenant->row_id,
                ];
                $user = User::query()->where('email', $payload['email'])->first();

                if ($user === null) {
                    $user = User::query()->create([
                        ...$values,
                        'public_id' => (string) Str::ulid(),
                        'email' => (string) $payload['email'],
                        'phone' => 'pending-wa-'.substr(md5((string) Str::ulid()), 0, 8),
                        'username' => 'holding_'.Str::lower(Str::random(16)),
                    ]);
                } else {
                    $user->forceFill($values)->save();
                }

                TenantMembership::query()->updateOrCreate(
                    ['user_id' => $user->row_id],
                    ['tenant_id' => $tenant->row_id, 'status' => 'active', 'joined_at' => now()],
                );

                return $user->fresh() ?? $user;
            });
            $this->syncRole($tenant, $user, self::ROLE_MAP[(string) $payload['role']]);
        } catch (Throwable) {
            return $this->redirectFailed($request);
        }

        if (Auth::check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->route('dashboard');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isValidPayload(array $payload): bool
    {
        return is_string($payload['email'] ?? null)
            && filter_var($payload['email'], FILTER_VALIDATE_EMAIL) !== false
            && is_string($payload['name'] ?? null)
            && $payload['name'] !== ''
            && is_string($payload['role'] ?? null)
            && $payload['role'] !== ''
            && array_key_exists('sub_tenant_code', $payload)
            && ($payload['sub_tenant_code'] === null || is_string($payload['sub_tenant_code']))
            && is_numeric($payload['exp'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function expiresAt(array $payload): int
    {
        return (int) $payload['exp'];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hasValidSignature(array $payload): bool
    {
        $secret = (string) config('services.holding_sso.secret');
        $signature = $payload['signature'] ?? null;

        if ($secret === '') {
            return $signature === null;
        }

        if (! is_string($signature)) {
            return false;
        }

        unset($payload['signature']);

        return hash_equals(hash_hmac('sha256', json_encode($payload), $secret), $signature);
    }

    private function defaultTenant(): Tenant
    {
        $code = (string) config('tenancy.local_tenant', '');
        $tenant = $code === ''
            ? null
            : Tenant::query()->with(['placement.shard'])
                ->whereIn('status', ['active', 'read_only'])
                ->where('code', $code)
                ->first();

        $tenant ??= Tenant::query()->with(['placement.shard'])
            ->whereIn('status', ['active', 'read_only'])
            ->orderBy('row_id')
            ->first();

        if ($tenant === null) {
            throw new RuntimeException('No default tenant is available.');
        }

        return $tenant;
    }

    private function activateTenant(Tenant $tenant, TenantContext $context, ShardConnectionManager $connections): void
    {
        $tenant->loadMissing('placement.shard');
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new RuntimeException('Tenant placement is incomplete.');
        }

        $connections->connect($shard);
        $context->initialize($tenant, $placement, $shard);
    }

    private function syncRole(Tenant $tenant, User $user, string $role): void
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role],
            [
                'name' => (string) (config("permissions.roles.{$role}.name") ?? $role),
                'is_system' => true,
            ],
        );

        UserRole::query()->updateOrCreate(
            ['platform_user_id' => $user->row_id, 'role_row_id' => $roleModel->row_id],
            [],
        );
    }

    private function redirectFailed(Request $request, string $message = 'Sesi masuk cepat tidak valid atau sudah kedaluwarsa.'): RedirectResponse
    {
        Auth::guard('web')->logout();

        return redirect()->route('login')->with('error', $message);
    }
}

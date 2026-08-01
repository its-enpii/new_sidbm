<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Membership\Models\OrganizationProfile;
use App\Support\AssistantSettingsResolver;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $request->user()?->only([
                    'row_id',
                    'public_id',
                    'name',
                    'username',
                    'email',
                    'status',
                    'is_superadmin',
                ]),
                'permissions' => $this->resolvePermissions($request),
                'nav_map' => config('permissions.nav_map', []),
            ],
            'flash' => $this->resolveFlash($request),
            'logoPath' => $this->resolveLogoPath(),
            'assistant' => $this->resolveAssistant($request),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvePermissions(Request $request): array
    {
        $user = $request->user();
        if ($user === null) {
            return [];
        }

        try {
            return app(PermissionChecker::class)->listFor($user);
        } catch (Throwable) {
            return $user->is_superadmin === true ? ['*'] : [];
        }
    }

    /**
     * @return array{enabled: bool, public_url: ?string}
     */
    private function resolveAssistant(Request $request): array
    {
        $enabled = AssistantSettingsResolver::widgetEnabled()
            && AssistantSettingsResolver::orchestratorBaseUrl() !== ''
            && AssistantSettingsResolver::sharedSecret() !== ''
            && $request->user() !== null;

        return [
            'enabled' => $enabled,
            'public_url' => $enabled ? AssistantSettingsResolver::orchestratorPublicUrl() : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveFlash(Request $request): array
    {
        $session = $request->hasSession() ? $request->session() : null;
        if ($session === null) {
            return [];
        }

        $flash = [];
        foreach (['success', 'error', 'warning', 'info'] as $key) {
            $value = $session->get($key);
            if ($value !== null) {
                $flash[$key] = $value;
            }
        }

        return $flash;
    }

    private function resolveLogoPath(): ?string
    {
        try {
            $context = app(TenantContext::class);
            if (! $context->isInitialized()) {
                return null;
            }
            $profile = OrganizationProfile::query()->first(['logo_path']);
            $path = $profile?->logo_path;
            if (! is_string($path) || $path === '') {
                return null;
            }

            return asset('storage/'.ltrim($path, '/'));
        } catch (\Throwable) {
            return null;
        }
    }
}

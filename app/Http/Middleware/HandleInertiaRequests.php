<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            ],
            'flash' => $this->resolveFlash($request),
            'logoPath' => $this->resolveLogoPath(),
            'assistant' => $this->resolveAssistant($request),
        ];
    }

    /**
     * @return array{enabled: bool}
     */
    private function resolveAssistant(Request $request): array
    {
        $enabled = (bool) config('encompletion.widget_enabled')
            && is_string(config('encompletion.base_url'))
            && config('encompletion.base_url') !== ''
            && is_string(config('encompletion.tenant_api_key'))
            && config('encompletion.tenant_api_key') !== ''
            && $request->user() !== null;

        return ['enabled' => $enabled];
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

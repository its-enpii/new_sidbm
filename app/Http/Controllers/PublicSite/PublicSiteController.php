<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PublicSiteController
{
    /**
     * Public entry point. On a platform host (or an unknown host) this renders
     * the SIDBM marketing page; on a tenant's custom domain it renders the
     * tenant's own branded landing page so visitors see the organization that
     * owns the domain, not the vendor.
     */
    public function home(Request $request): Response|RedirectResponse
    {
        if (config('desktop.enabled') || $request->header('X-Desktop-Client') === '1') {
            return redirect()->route('login');
        }

        $context = app(TenantContext::class);

        // ResolvePublicSite clears the context in its finally block, so the
        // landing data is gathered here while the request is still inside it.
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', [
                'name' => config('app.name'),
                'status' => 'ok',
            ]);
        }

        return Inertia::render('PublicSite/TenantHome', $site);
    }

    /**
     * @return array<string, mixed>|null null when no tenant resolved for the host
     */
    private function resolveTenantSite(TenantContext $context): ?array
    {
        if (! $context->isInitialized()) {
            return null;
        }

        $tenant = $context->tenant();

        if ($tenant->status === 'suspended') {
            return null;
        }

        $profile = OrganizationProfile::query()->first();

        $displayName = $profile?->displayName() ?: (string) $tenant->name;

        return [
            'organization' => [
                'name' => $displayName,
                'legal_name' => $profile?->legal_name ?: (string) $tenant->name,
                'address' => $this->composeAddress($profile),
                'phone' => $profile?->phone,
                'email' => $profile?->email,
                'website' => $profile?->website,
                'logo_url' => $profile?->logo_url,
                'operational_start_year' => $profile?->operational_start_date?->year,
                'district_name' => $profile?->district_name,
                'regency_name' => $profile?->regency_name,
            ],
            'tenant' => [
                'code' => $tenant->code,
                'is_training_mode' => $tenant->isTraining(),
            ],
        ];
    }

    private function composeAddress(?OrganizationProfile $profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $parts = array_filter([
            $profile->address,
            $profile->district_name,
            $profile->regency_name,
        ], fn (?string $part): bool => is_string($part) && trim($part) !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}

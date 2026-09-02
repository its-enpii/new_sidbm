<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Website\Models\SitePage;
use App\Domain\Website\Models\SitePost;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
     * Public blog index for the resolved tenant domain. Platform hosts and
     * unknown hosts fall back to the vendor home so stray links never 404.
     */
    public function posts(Request $request): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $search = trim((string) $request->query('q', ''));

        $posts = SitePost::query()
            ->published()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")))
            ->orderByDesc('published_at')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (SitePost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_url' => $post->cover_image_path !== null ? Storage::disk('public')->url($post->cover_image_path) : null,
                'published_at' => $post->published_at?->toIso8601String(),
            ]);

        return Inertia::render('PublicSite/BlogIndex', [
            ...$site,
            'posts' => $posts,
            'search' => $search,
        ]);
    }

    /**
     * Public blog post detail for the resolved tenant domain.
     */
    public function post(Request $request, string $slug): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $post = SitePost::query()->published()->where('slug', $slug)->first();

        if ($post === null) {
            return Inertia::render('PublicSite/BlogIndex', [
                ...$site,
                'posts' => SitePost::query()->published()->orderByDesc('published_at')->paginate(9),
                'search' => '',
            ]);
        }

        return Inertia::render('PublicSite/BlogPost', [
            ...$site,
            'post' => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'cover_image_url' => $post->cover_image_path !== null ? Storage::disk('public')->url($post->cover_image_path) : null,
                'published_at' => $post->published_at?->toIso8601String(),
                'author_name' => $post->author_name,
                'meta_description' => $post->meta_description,
            ],
        ]);
    }

    /**
     * Public static page detail for the resolved tenant domain.
     */
    public function page(Request $request, string $slug): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->resolveTenantSite($context);

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $page = SitePage::query()->published()->where('slug', $slug)->first();

        if ($page === null) {
            // Unknown slugs stay on the tenant's own branding; the vendor page
            // only belongs to platform hosts.
            return Inertia::render('PublicSite/TenantHome', $site);
        }

        return Inertia::render('PublicSite/StaticPage', [
            ...$site,
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'content' => $page->content,
                'meta_description' => $page->meta_description,
            ],
        ]);
    }

    private function shouldRedirectToVendor(Request $request): bool
    {
        return config('desktop.enabled') || $request->header('X-Desktop-Client') === '1';
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

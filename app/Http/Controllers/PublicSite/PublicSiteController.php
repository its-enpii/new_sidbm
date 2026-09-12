<?php

declare(strict_types=1);

namespace App\Http\Controllers\PublicSite;

use App\Domain\Website\Models\SiteMessage;
use App\Domain\Website\Services\PublicSiteContentService;
use App\Http\Requests\PublicSite\SiteMessageRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class PublicSiteController
{
    public function __construct(
        private readonly PublicSiteContentService $content,
    ) {}

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
        $site = $this->content->tenantSite();
        $this->shareMeta($request, path: '/', jsonLd: $site === null ? [] : [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $site['organization']['name'],
                'logo' => $site['organization']['logo_url'],
                'url' => $request->getSchemeAndHttpHost().'/',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $site['organization']['name'],
                'url' => $request->getSchemeAndHttpHost().'/',
            ],
        ]);

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
        $site = $this->content->tenantSite();

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $search = trim((string) $request->query('q', ''));
        $this->shareBlogIndexMeta($request, $site);

        return Inertia::render('PublicSite/BlogIndex', [...$site, ...$this->content->posts($search)]);
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
        $site = $this->content->tenantSite();

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $post = $this->content->post($slug);

        if ($post === null) {
            $this->shareBlogIndexMeta($request, $site);

            return Inertia::render('PublicSite/BlogIndex', [
                ...$site,
                ...$this->content->posts(''),
            ]);
        }

        $this->shareMeta($request, path: '/berita/'.$post->slug, jsonLd: [
            [
                '@context' => 'https://schema.org',
                '@type' => 'NewsArticle',
                'headline' => $post->title,
                'datePublished' => $post->published_at?->toIso8601String(),
                'image' => $this->content->postData($post)['cover_image_url'],
                'author' => [
                    '@type' => 'Organization',
                    'name' => $site['organization']['name'],
                ],
            ],
        ]);

        return Inertia::render('PublicSite/BlogPost', [
            ...$site,
            'post' => $this->content->postData($post),
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
        $site = $this->content->tenantSite();

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        $page = $this->content->page($slug);

        $this->shareMeta($request, path: $page !== null ? '/p/'.$page->slug : '/');

        if ($page === null) {
            // Unknown slugs stay on the tenant's own branding; the vendor page
            // only belongs to platform hosts.
            return Inertia::render('PublicSite/TenantHome', $site);
        }

        return Inertia::render('PublicSite/StaticPage', [
            ...$site,
            'page' => $this->content->pageData($page),
        ]);
    }

    private function shareBlogIndexMeta(Request $request, array $site): void
    {
        $base = $request->getSchemeAndHttpHost();
        $this->shareMeta($request, path: '/berita', jsonLd: [
            [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => 'Berita — '.$site['organization']['name'],
                'url' => $base.'/berita',
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    [
                        '@type' => 'ListItem',
                        'position' => 1,
                        'name' => $site['organization']['name'],
                        'item' => $base.'/',
                    ],
                    [
                        '@type' => 'ListItem',
                        'position' => 2,
                        'name' => 'Berita',
                        'item' => $base.'/berita',
                    ],
                ],
            ],
        ]);
    }

    private function shouldRedirectToVendor(Request $request): bool
    {
        return config('desktop.enabled') || $request->header('X-Desktop-Client') === '1';
    }

    /**
     * Public contact page for the resolved tenant domain.
     */
    public function contact(Request $request): Response|RedirectResponse
    {
        if ($this->shouldRedirectToVendor($request)) {
            return redirect()->route('home');
        }

        $context = app(TenantContext::class);
        $site = $this->content->tenantSite();

        if ($site === null) {
            return Inertia::render('Home', ['name' => config('app.name'), 'status' => 'ok']);
        }

        return Inertia::render('PublicSite/Contact', [
            ...$site,
            'settings' => $this->content->settings(),
        ]);
    }

    /**
     * JSON-LD is injected by app.blade.php so crawlers receive structured data
     * without waiting for client-side JavaScript.
     *
     * @param  array<string, mixed>  $jsonLd
     */
    private function shareMeta(Request $request, string $path, array $jsonLd = []): void
    {
        config()->set('inertia.public_site', [
            'path' => $path,
            'json_ld' => array_map(
                static fn (array $schema): string => json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                $jsonLd,
            ),
        ]);
    }

    /**
     * Store a public contact-form submission for the resolved tenant.
     * Rate-limited at the route level; a hidden honeypot field silently
     * drops obvious bot submissions.
     */
    public function storeMessage(SiteMessageRequest $request): RedirectResponse
    {
        $context = app(TenantContext::class);

        if (! $context->isInitialized()) {
            return redirect()->route('home');
        }

        $validated = $request->validated();

        // Honeypot: real users never see the "website" field. Pretend success
        // so bots do not learn they were caught.
        if (trim((string) ($validated['website'] ?? '')) !== '') {
            return redirect()->back()->with('success', 'Pesan berhasil dikirim. Terima kasih!');
        }

        SiteMessage::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
        ]);

        return redirect()->back()->with('success', 'Pesan berhasil dikirim. Terima kasih!');
    }

    /**
     * Sitemap for the resolved tenant domain; platform hosts get an empty
     * sitemap pointing at the vendor home only.
     */
    public function sitemap(Request $request): SymfonyResponse
    {
        $context = app(TenantContext::class);
        $urls = $this->content->sitemapUrls();

        return response()
            ->view('public.sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }

    /**
     * robots.txt on tenant domains allows crawling of the public site but
     * never the authenticated app; platform hosts likewise block /website,
     * /dashboard and friends.
     */
    public function robots(Request $request): SymfonyResponse
    {
        $lines = [
            'User-agent: *',
            'Disallow: /login',
            'Disallow: /dashboard',
            'Disallow: /website',
            'Disallow: /master-data',
            'Disallow: /lending',
            'Disallow: /accounting',
            'Disallow: /settings',
            'Disallow: /admin',
            '',
            'Sitemap: '.route('public.sitemap'),
        ];

        return response(implode("\n", $lines))->header('Content-Type', 'text/plain');
    }
}

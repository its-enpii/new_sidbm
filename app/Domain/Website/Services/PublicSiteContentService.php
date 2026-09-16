<?php

declare(strict_types=1);

namespace App\Domain\Website\Services;

use App\Domain\Membership\Models\OrganizationProfile;
use App\Domain\Website\Models\SitePage;
use App\Domain\Website\Models\SitePost;
use App\Domain\Website\Models\SiteSetting;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Storage;

final readonly class PublicSiteContentService
{
    public function __construct(
        private TenantContext $context,
    ) {}

    /**
     * Public site payload. Returns null when no tenant is bound to the host,
     * the tenant is suspended, or the tenant keeps the site in draft state.
     *
     * @return array<string, mixed>|null
     */
    public function tenantSite(): ?array
    {
        $site = $this->site(includeDrafts: false);

        return $site === null ? null : $site;
    }

    /**
     * Same payload as tenantSite() but ignoring the publication state, used by
     * the authenticated builder preview so drafts stay visible to editors.
     *
     * @return array<string, mixed>|null
     */
    public function tenantPreview(): ?array
    {
        return $this->site(includeDrafts: true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function site(bool $includeDrafts): ?array
    {
        if (! $this->context->isInitialized() || $this->context->tenant()->status === 'suspended') {
            return null;
        }

        $profile = OrganizationProfile::query()->first();
        $tenant = $this->context->tenant();
        $displayName = $profile?->displayName() ?: (string) $tenant->name;
        $settings = $this->settings();

        // Draft sites stay invisible to visitors while remaining previewable
        // from the builder.
        if (! $includeDrafts && ! ($settings['is_published'] ?? true)) {
            return null;
        }

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
            'settings' => $settings,
            'recent_posts' => $this->featuredPosts(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = SiteSetting::query()->first();

        return [
            'template' => $settings?->template ?: 'classic',
            'hero_tagline' => $settings?->hero_tagline,
            'hero_description' => $settings?->hero_description,
            'hero_image_url' => $settings?->hero_image_path
                ? Storage::disk('public')->url($settings->hero_image_path)
                : null,
            'about_short' => $settings?->about_short,
            'social' => [
                'facebook' => $settings?->facebook_url,
                'instagram' => $settings?->instagram_url,
                'youtube' => $settings?->youtube_url,
            ],
            'contact_phone' => $settings?->contact_phone,
            'contact_email' => $settings?->contact_email,
            'contact_address' => $settings?->contact_address,
            'footer_note' => $settings?->footer_note,
            'sections_config' => SiteSetting::sectionsConfigFor(is_array($settings?->sections_config) ? $settings->sections_config : null),
            'officers_data' => $this->officers($settings?->officers_data),
            'is_published' => $settings === null ? true : (bool) ($settings->is_published ?? true),
        ];
    }

    /**
     * Six latest published posts for the tenant home post section.
     *
     * @return array<int, array<string, mixed>>
     */
    public function featuredPosts(int $limit = 6): array
    {
        return SitePost::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['id', 'slug', 'title', 'excerpt', 'cover_image_path', 'published_at', 'author_name'])
            ->map(fn (SitePost $post): array => [
                'id' => (int) $post->getAttribute('id'),
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'cover_image_url' => $this->coverImageUrl($post->cover_image_path),
                'published_at' => $post->published_at?->toIso8601String(),
                'author_name' => $post->author_name,
            ])
            ->all();
    }

    /**
     * Normalize the stored officers list: drop empty rows and attach public
     * photo URLs for freshly uploaded (non-absolute) paths.
     *
     * @return array<int, array<string, mixed>>
     */
    public function officers(mixed $officers): array
    {
        if (! is_array($officers)) {
            return [];
        }

        return array_values(array_filter(
            array_map($this->normalizeOfficer(...), $officers),
            fn (?array $officer): bool => $officer !== null,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeOfficer(mixed $officer): ?array
    {
        if (! is_array($officer)) {
            return null;
        }

        $name = trim((string) ($officer['name'] ?? ''));
        if ($name === '') {
            return null;
        }

        $photo = $officer['photo_path'] ?? null;
        $photo = is_string($photo) && $photo !== '' ? $photo : null;

        return [
            'name' => $name,
            'position' => $this->textOrNull($officer['position'] ?? null),
            'phone' => $this->textOrNull($officer['phone'] ?? null),
            'email' => $this->textOrNull($officer['email'] ?? null),
            'social' => $this->textOrNull($officer['social'] ?? null),
            'photo_url' => $this->officerPhotoUrl($photo),
            'photo_path' => $photo,
        ];
    }

    private function officerPhotoUrl(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    private function textOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @return array<string, mixed>
     */
    public function posts(string $search): array
    {
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
                'cover_image_url' => $this->coverImageUrl($post->cover_image_path),
                'published_at' => $post->published_at?->toIso8601String(),
            ]);

        return [
            'posts' => $posts,
            'search' => $search,
        ];
    }

    public function post(string $slug): ?SitePost
    {
        return SitePost::query()->published()->where('slug', $slug)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function postData(SitePost $post): array
    {
        return [
            'slug' => $post->slug,
            'title' => $post->title,
            'excerpt' => $post->excerpt,
            'content' => $post->content,
            'cover_image_url' => $this->coverImageUrl($post->cover_image_path),
            'published_at' => $post->published_at?->toIso8601String(),
            'author_name' => $post->author_name,
            'meta_description' => $post->meta_description,
        ];
    }

    public function page(string $slug): ?SitePage
    {
        return SitePage::query()->published()->where('slug', $slug)->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function pageData(SitePage $page): array
    {
        return [
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'meta_description' => $page->meta_description,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function recentPosts(int $limit = 15): array
    {
        return SitePost::query()
            ->published()
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get(['slug', 'title', 'excerpt', 'published_at'])
            ->map(fn (SitePost $post): array => [
                'slug' => $post->slug,
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'published_at' => $post->published_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array{loc: string, lastmod: ?string, changefreq: string, priority: string}>
     */
    public function sitemapUrls(): array
    {
        $urls = [[
            'loc' => route('home'),
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '1.0',
        ]];

        // Legal documents exist on every host (platform and tenant alike), so
        // they are advertised before the tenant gate below.
        foreach ([route('public.terms'), route('public.privacy')] as $legalUrl) {
            $urls[] = [
                'loc' => $legalUrl,
                'lastmod' => LegalDocumentService::LAST_UPDATED,
                'changefreq' => 'yearly',
                'priority' => '0.4',
            ];
        }

        if (! $this->context->isInitialized() || $this->context->tenant()->status === 'suspended') {
            return $urls;
        }

        foreach (SitePost::query()->published()->orderByDesc('published_at')->get(['slug', 'updated_at']) as $post) {
            $urls[] = [
                'loc' => route('public.post', $post->slug),
                'lastmod' => $post->updated_at?->toIso8601String(),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        foreach (SitePage::query()->published()->orderBy('slug')->get(['slug', 'updated_at']) as $page) {
            $urls[] = [
                'loc' => route('public.page', $page->slug),
                'lastmod' => $page->updated_at?->toIso8601String(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];
        }

        $urls[] = [
            'loc' => route('public.posts'),
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'daily',
            'priority' => '0.8',
        ];

        $urls[] = [
            'loc' => route('public.contact'),
            'lastmod' => now()->toIso8601String(),
            'changefreq' => 'monthly',
            'priority' => '0.6',
        ];

        return $urls;
    }

    private function coverImageUrl(?string $path): ?string
    {
        return $path !== null ? Storage::disk('public')->url($path) : null;
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

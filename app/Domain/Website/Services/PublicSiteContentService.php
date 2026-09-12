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
     * @return array<string, mixed>|null
     */
    public function tenantSite(): ?array
    {
        if (! $this->context->isInitialized() || $this->context->tenant()->status === 'suspended') {
            return null;
        }

        $profile = OrganizationProfile::query()->first();
        $tenant = $this->context->tenant();
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
            'settings' => $this->settings(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $settings = SiteSetting::query()->first();

        return [
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
        ];
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

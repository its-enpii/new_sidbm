<?php

declare(strict_types=1);

namespace App\Http\Controllers\Website;

use App\Domain\Access\Services\PermissionChecker;
use App\Domain\Website\Models\SiteSetting;
use App\Domain\Website\Services\PublicSiteContentService;
use App\Http\Requests\Website\SiteSettingRequest;
use App\Services\TenantImpersonationService;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-facing website builder: template choice, section customization and
 * publication state for the tenant's public site.
 */
final class WebsiteSettingController
{
    /**
     * Sub-directory of the public disk holding officer portrait uploads.
     */
    private const OFFICER_IMAGE_DIR = 'site/officers';

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly PublicSiteContentService $content,
        private readonly TenantContext $context,
        private readonly TenantImpersonationService $impersonation,
    ) {}

    public function edit(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.view');

        $settings = SiteSetting::query()->first();
        $sections = SiteSetting::sectionsConfigFor(
            is_array($settings?->sections_config) ? $settings->sections_config : null,
        );

        return Inertia::render('Website/Settings/Form', [
            'settings' => [
                'template' => $settings?->template ?: 'classic',
                'hero_tagline' => $settings?->hero_tagline,
                'hero_description' => $settings?->hero_description,
                'about_short' => $settings?->about_short,
                'facebook_url' => $settings?->facebook_url,
                'instagram_url' => $settings?->instagram_url,
                'youtube_url' => $settings?->youtube_url,
                'contact_phone' => $settings?->contact_phone,
                'contact_email' => $settings?->contact_email,
                'contact_address' => $settings?->contact_address,
                'footer_note' => $settings?->footer_note,
                'is_published' => $settings === null ? true : (bool) ($settings->is_published ?? true),
                'sections_config' => $sections,
                'officers_data' => $this->content->officers($settings?->officers_data),
            ],
            'heroImageUrl' => $settings?->hero_image_path
                ? Storage::disk('public')->url($settings->hero_image_path)
                : null,
            'recentPosts' => $this->content->featuredPosts(),
            'siteStatus' => [
                'public_url' => $this->publicUrl($request),
                'preview_url' => route('website.preview'),
            ],
        ]);
    }

    /**
     * Live preview of the tenant's public site, rendered with the very same
     * payload the visitor-facing landing page uses, except that a site kept in
     * draft state still renders so editors can review it before publishing.
     */
    public function preview(Request $request): Response
    {
        $this->permissions->denyUnless($request->user(), 'website.view');

        $site = $this->content->tenantPreview() ?? [];

        return Inertia::render('PublicSite/TenantHome', [
            ...$site,
            'is_preview' => true,
        ]);
    }

    public function update(SiteSettingRequest $request): RedirectResponse
    {
        // Editing settings is destructive user input (writes & disk) — treat
        // as manage, even though viewing the form only needs view.
        $this->permissions->denyUnless($request->user(), 'website.manage');

        $settings = SiteSetting::query()->first();

        $attributes = collect($validated = $request->validated())->except([
            'hero_image',
            'remove_hero_image',
            'sections_config',
            'officers_data',
        ])->all();

        if (array_key_exists('is_published', $attributes)) {
            $attributes['is_published'] = $request->boolean('is_published');
        }

        // Builder payloads carry the JSONB columns; older integrations that
        // PUT only flat fields must not silently wipe section config or the
        // officer roster.
        if (array_key_exists('sections_config', $validated)) {
            $attributes['sections_config'] = $this->cleanSections($validated['sections_config']);
        }

        if ($request->boolean('remove_hero_image')) {
            if ($settings?->hero_image_path) {
                Storage::disk('public')->delete($settings->hero_image_path);
            }

            $attributes['hero_image_path'] = null;
        }

        if ($request->hasFile('hero_image')) {
            $oldPath = $settings?->hero_image_path;
            $path = $request->file('hero_image')->store('site/settings', 'public');

            if (is_string($oldPath) && $oldPath !== '') {
                Storage::disk('public')->delete($oldPath);
            }

            $attributes['hero_image_path'] = $path;
        }

        $officerFiles = [];

        if (array_key_exists('officers_data', $validated)) {
            [$attributes['officers_data'], $officerFiles] = $this->officersPayload(
                $validated['officers_data'],
                $settings?->officers_data,
            );
        }

        if ($settings === null) {
            $settings = SiteSetting::query()->create($attributes);
        } else {
            $settings->update($attributes);
        }

        // Officer portraits are stored relative to the public disk so the same
        // JSONB row works offline and across desktop sync.
        foreach ($officerFiles as $index => $file) {
            $officers = $settings->officers_data ?? [];

            if (! isset($officers[$index])) {
                continue;
            }

            $officers[$index]['photo_path'] = $file->store(self::OFFICER_IMAGE_DIR, 'public');
            $settings->update(['officers_data' => array_values($officers)]);
        }

        return to_route('website.settings.edit')->with('success', 'Pengaturan situs berhasil disimpan.');
    }

    /**
     * Normalize validated officer rows and return them together with the
     * uploaded portraits keyed by their final row index.
     *
     * @param  mixed  $validated  officers_data rows from the form request
     * @param  mixed  $stored  rows already persisted on the settings model
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, UploadedFile>}
     */
    private function officersPayload(mixed $validated, mixed $stored): array
    {
        $storedRows = is_array($stored) ? array_values($stored) : [];
        $rows = [];
        $files = [];
        $keptPaths = [];

        foreach (is_array($validated) ? $validated : [] as $index => $officer) {
            if (! is_array($officer)) {
                continue;
            }

            $file = $officer['photo'] ?? null;
            $existing = trim((string) ($officer['photo_path'] ?? ''));
            $photo = $existing === '' ? null : $existing;

            if ($file instanceof UploadedFile) {
                // A fresh upload replaces whatever portrait the row had.
                if ($photo !== null && $this->isLocalPath($photo)) {
                    Storage::disk('public')->delete($photo);
                }

                $photo = null;
                $files[$index] = $file;
            }

            if ($photo !== null && $this->isLocalPath($photo)) {
                $keptPaths[] = $photo;
            }

            $rows[] = [
                'name' => trim((string) ($officer['name'] ?? '')),
                'position' => $this->nullableText($officer['position'] ?? null),
                'phone' => $this->nullableText($officer['phone'] ?? null),
                'email' => $this->nullableText($officer['email'] ?? null),
                'social' => $this->nullableText($officer['social'] ?? null),
                'photo_path' => $photo,
            ];
        }

        // Drop portraits that disappeared from the list (row removed or replaced).
        foreach ($storedRows as $officer) {
            $path = is_array($officer) ? trim((string) ($officer['photo_path'] ?? '')) : '';

            if ($path !== '' && $this->isLocalPath($path) && ! in_array($path, $keptPaths, true)) {
                Storage::disk('public')->delete($path);
            }
        }

        return [$rows, $files];
    }

    /**
     * Rebuild the section config from the known schema so the JSONB column can
     * never carry unknown keys, empty list items, or stringly-typed booleans.
     *
     * @param  mixed  $payload  sections_config from the validated request
     * @return array<string, array<string, mixed>>
     */
    private function cleanSections(mixed $payload): array
    {
        $payload = is_array($payload) ? $payload : [];

        $clean = [];

        foreach (SiteSetting::DEFAULT_SECTIONS as $section => $fields) {
            $input = is_array($payload[$section] ?? null) ? $payload[$section] : [];

            foreach ($fields as $field => $default) {
                $value = array_key_exists($field, $input) ? $input[$field] : $default;

                $clean[$section][$field] = match (true) {
                    is_array($default) => $this->stringList($value),
                    $default === true || $default === false => (bool) $value,
                    $field === 'year_founded' => $this->yearOrNull($value),
                    default => $this->nullableText($value),
                };
            }
        }

        /** @var array<string, array<string, mixed>> $clean */
        return $clean;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(fn (mixed $item): string => trim((string) $item), $value),
            fn (string $item): bool => $item !== '',
        ));
    }

    private function yearOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        $digits = trim((string) $value);

        return $digits === '' || ! ctype_digit($digits) ? null : (int) $digits;
    }

    private function nullableText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function isLocalPath(string $path): bool
    {
        return ! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://');
    }

    /**
     * Public entry point of the tenant site: the first custom domain when one
     * is registered, otherwise the current host.
     */
    private function publicUrl(Request $request): string
    {
        if (! $this->context->isInitialized()) {
            return (string) config('app.url', '/');
        }

        return $this->impersonation->resolveTenantBaseUrl(
            $this->context->tenant(),
            request: $request,
        );
    }
}

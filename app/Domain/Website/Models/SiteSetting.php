<?php

declare(strict_types=1);

namespace App\Domain\Website\Models;

use App\Domain\Sync\Contracts\ExcludedFromDesktopSync;
use App\Models\Tenant\TenantModel;

final class SiteSetting extends TenantModel implements ExcludedFromDesktopSync
{
    /**
     * Templates selectable in the public-site builder.
     */
    public const TEMPLATES = ['classic', 'modern', 'minimal'];

    /**
     * Default section copy/visibility used whenever a tenant has never saved
     * the builder (or saved before a section existed).
     */
    public const DEFAULT_SECTIONS = [
        'hero' => [
            'enabled' => true,
            'title' => null,
            'cta_label' => null,
            'cta_target' => null,
        ],
        'about' => [
            'enabled' => true,
            'title' => 'Tentang Kami',
            'vision' => null,
            'mission' => [],
            'values' => [],
            'year_founded' => null,
        ],
        'posts' => [
            'enabled' => true,
            'title' => 'Kabar & Pengumuman',
            'subtitle' => null,
        ],
        'officers' => [
            'enabled' => true,
            'title' => 'Struktur Organisasi',
            'subtitle' => null,
        ],
        'contact' => [
            'enabled' => true,
            'title' => 'Kontak Kami',
            'office_hours' => null,
            'contact_form_enabled' => true,
        ],
    ];

    protected $table = 'site_settings';

    protected function casts(): array
    {
        return [
            'sections_config' => 'array',
            'officers_data' => 'array',
            'is_published' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSectionsConfig(): array
    {
        return self::DEFAULT_SECTIONS;
    }

    /**
     * Section configuration merged over the defaults so every key the public
     * site reads is always present.
     *
     * @return array<string, mixed>
     */
    public static function sectionsConfigFor(?array $config): array
    {
        return array_replace_recursive(self::DEFAULT_SECTIONS, is_array($config) ? $config : []);
    }
}

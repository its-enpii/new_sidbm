<?php

declare(strict_types=1);

namespace App\Http\Requests\Website;

use App\Domain\Website\Models\SiteSetting;
use App\Http\Requests\Concerns\AuthorizesPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class SiteSettingRequest extends FormRequest
{
    use AuthorizesPermission;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'template' => ['nullable', 'string', 'in:'.implode(',', SiteSetting::TEMPLATES)],
            'hero_tagline' => ['nullable', 'string', 'max:200'],
            'hero_description' => ['nullable', 'string', 'max:500'],
            'hero_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_hero_image' => ['nullable', 'boolean'],
            'about_short' => ['nullable', 'string', 'max:500'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'youtube_url' => ['nullable', 'url', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'footer_note' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],

            // — Section builder (JSONB columns) —
            // Nested rules are declared per key so validated() strips anything
            // the builder does not own (Laravel excludes unvalidated array keys).
            'sections_config' => ['nullable', 'array'],
            'sections_config.hero' => ['sometimes', 'array'],
            'sections_config.hero.enabled' => ['sometimes', 'boolean'],
            'sections_config.hero.title' => ['nullable', 'string', 'max:200'],
            'sections_config.hero.cta_label' => ['nullable', 'string', 'max:120'],
            'sections_config.hero.cta_target' => ['nullable', 'string', 'max:255'],
            'sections_config.about' => ['sometimes', 'array'],
            'sections_config.about.enabled' => ['sometimes', 'boolean'],
            'sections_config.about.title' => ['nullable', 'string', 'max:200'],
            'sections_config.about.vision' => ['nullable', 'string', 'max:1000'],
            'sections_config.about.mission' => ['nullable', 'array'],
            'sections_config.about.mission.*' => ['nullable', 'string', 'max:500'],
            'sections_config.about.values' => ['nullable', 'array'],
            'sections_config.about.values.*' => ['nullable', 'string', 'max:200'],
            'sections_config.about.year_founded' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'sections_config.posts' => ['sometimes', 'array'],
            'sections_config.posts.enabled' => ['sometimes', 'boolean'],
            'sections_config.posts.title' => ['nullable', 'string', 'max:200'],
            'sections_config.posts.subtitle' => ['nullable', 'string', 'max:500'],
            'sections_config.officers' => ['sometimes', 'array'],
            'sections_config.officers.enabled' => ['sometimes', 'boolean'],
            'sections_config.officers.title' => ['nullable', 'string', 'max:200'],
            'sections_config.officers.subtitle' => ['nullable', 'string', 'max:500'],
            'sections_config.contact' => ['sometimes', 'array'],
            'sections_config.contact.enabled' => ['sometimes', 'boolean'],
            'sections_config.contact.title' => ['nullable', 'string', 'max:200'],
            'sections_config.contact.office_hours' => ['nullable', 'string', 'max:200'],
            'sections_config.contact.contact_form_enabled' => ['sometimes', 'boolean'],

            'officers_data' => ['nullable', 'array'],
            'officers_data.*.name' => ['required', 'string', 'max:120'],
            'officers_data.*.position' => ['nullable', 'string', 'max:120'],
            'officers_data.*.phone' => ['nullable', 'string', 'max:40'],
            'officers_data.*.email' => ['nullable', 'email', 'max:255'],
            'officers_data.*.social' => ['nullable', 'string', 'max:255'],
            'officers_data.*.photo_path' => ['nullable', 'string', 'max:500'],
            'officers_data.*.photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * Officer rows are optional; empty drafts (no name typed yet) are dropped
     * before validation so a half-filled last row never blocks a save.
     *
     * @return array<string, mixed>
     */
    protected function prepareForValidation(): void
    {
        $officers = $this->input('officers_data');

        if (! is_array($officers)) {
            return;
        }

        $this->merge([
            'officers_data' => array_values(array_filter(
                $officers,
                fn (mixed $officer): bool => is_array($officer)
                    && (trim((string) ($officer['name'] ?? '')) !== ''
                        || ($officer['photo'] ?? null) instanceof UploadedFile),
            )),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'template' => 'template',
            'hero_tagline' => 'tagline hero',
            'hero_description' => 'deskripsi hero',
            'hero_image' => 'gambar hero',
            'about_short' => 'tentang singkat',
            'facebook_url' => 'tautan Facebook',
            'instagram_url' => 'tautan Instagram',
            'youtube_url' => 'tautan YouTube',
            'contact_phone' => 'telepon',
            'contact_email' => 'email kontak',
            'contact_address' => 'alamat kontak',
            'footer_note' => 'catatan footer',
            'is_published' => 'status publikasi',
            'sections_config' => 'konfigurasi section',
            'sections_config.hero.enabled' => 'section hero aktif',
            'sections_config.about.enabled' => 'section tentang aktif',
            'sections_config.posts.enabled' => 'section berita aktif',
            'sections_config.officers.enabled' => 'section pengurus aktif',
            'sections_config.contact.enabled' => 'section kontak aktif',
            'officers_data' => 'daftar pengurus',
            'officers_data.*.name' => 'nama pengurus',
            'officers_data.*.position' => 'jabatan pengurus',
            'officers_data.*.email' => 'email pengurus',
            'officers_data.*.photo' => 'foto pengurus',
        ];
    }
}

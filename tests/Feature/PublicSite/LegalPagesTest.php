<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Tenancy\Services\PublicSiteResolver;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class LegalPagesTest extends TestCase
{
    use BuildsTenantTestDatabase;

    private const GOOGLEBOT = 'Googlebot/2.1 (+http://www.google.com/bot.html)';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rebuildTenantTestDatabases();
        $this->activateTenantDomain();
    }

    protected function tearDown(): void
    {
        $this->clearTenantTestContext();
        parent::tearDown();
    }

    public function test_can_access_terms_page_as_inertia_page(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/terms');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/LegalPage')
                ->where('document.type', 'terms')
                ->where('document.path', '/terms')
                ->where('document.title', 'Syarat Layanan')
                ->where('organization.name', 'Tenant A')
                ->has('document.sections', 15)
                ->has('legalDocuments', 2));
    }

    public function test_can_access_privacy_page_as_inertia_page(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/privacy');

        $response->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PublicSite/LegalPage')
                ->where('document.type', 'privacy')
                ->where('document.path', '/privacy')
                ->where('document.title', 'Kebijakan Privasi')
                ->where('organization.name', 'Tenant A')
                ->has('document.sections', 14)
                ->has('legalDocuments', 2));
    }

    public function test_terms_page_documents_every_required_legal_topic(): void
    {
        $headings = $this->sectionTitles('http://bumdes-sukamaju.test/terms');

        foreach ([
            'Akun, Otentikasi, dan Keamanan Akses',
            'Hak dan Kewajiban Pengguna',
            'Tata Kelola Data Finansial dan Pembukuan',
            'Batasan Tanggung Jawab',
            'Kepemilikan Data dan Kekayaan Intelektual',
            'Perubahan Ketentuan',
            'Kontak dan Kanal Bantuan',
        ] as $heading) {
            self::assertContains($heading, $headings, "Syarat Layanan kehilangan seksi {$heading}.");
        }
    }

    public function test_privacy_page_documents_every_required_legal_topic(): void
    {
        $headings = $this->sectionTitles('http://bumdes-sukamaju.test/privacy');

        foreach ([
            'Data Pribadi yang Dikumpulkan',
            'Dasar Hukum dan Tujuan Pemrosesan',
            'Kerahasiaan Data Keuangan Nasabah',
            'Keamanan dan Isolasi Data Multi-Tenant',
            'Hak Anda sebagai Subjek Data',
            'Retensi, Penghapusan, dan Pengarsipan Data',
            'Kontak dan Pengaduan Privasi',
        ] as $heading) {
            self::assertContains($heading, $headings, "Kebijakan Privasi kehilangan seksi {$heading}.");
        }
    }

    public function test_legal_pages_are_public_on_the_platform_host_too(): void
    {
        $this->clearTenantTestContext();

        foreach (['terms', 'privacy'] as $path) {
            $this->get("http://localhost/{$path}")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('PublicSite/LegalPage')
                    ->missing('organization'));
        }
    }

    public function test_googlebot_receives_server_rendered_legal_pages(): void
    {
        foreach ([
            'terms' => ['Syarat Layanan', 'Akun, Otentikasi, dan Keamanan Akses', '/terms'],
            'privacy' => ['Kebijakan Privasi', 'Keamanan dan Isolasi Data Multi-Tenant', '/privacy'],
        ] as $path => [$title, $section, $canonical]) {
            $response = $this->get("http://bumdes-sukamaju.test/{$path}", ['User-Agent' => self::GOOGLEBOT]);

            $response->assertOk();
            $response->assertHeader('X-Robots-HTML', 'bot-friendly');

            $content = (string) $response->getContent();

            // Full legal text is present in the raw HTML...
            self::assertStringContainsString($title, $content);
            self::assertStringContainsString($section, $content);
            self::assertStringContainsString('UU No. 27 Tahun 2022', $content);
            // ...and no SPA shell has to boot for the crawler to read it.
            self::assertStringNotContainsString('id="app"', $content);
            self::assertStringContainsString('<link rel="canonical" href="http://bumdes-sukamaju.test'.$canonical.'">', $content);

            $schema = $this->firstJsonLd($content);
            self::assertSame('WebPage', $schema['@type']);
            self::assertSame($title, $schema['name']);
            self::assertSame('id-ID', $schema['inLanguage']);
            self::assertNotEmpty($schema['dateModified']);
        }
    }

    public function test_browser_response_carries_legal_json_ld_for_crawlers(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/privacy', [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ]);

        $response->assertOk();
        $content = (string) $response->getContent();
        self::assertStringContainsString('id="app"', $content);

        $schema = $this->firstJsonLd($content);
        self::assertSame('WebPage', $schema['@type']);
        self::assertSame('Kebijakan Privasi', $schema['name']);
        self::assertStringContainsString('UU No. 27 Tahun 2022', (string) $content);
    }

    public function test_sitemap_contains_terms_and_privacy_urls(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/sitemap.xml');

        $response->assertOk();
        $xml = (string) $response->getContent();
        $xmlObject = simplexml_load_string($xml);
        self::assertIsObject($xmlObject);

        $entries = [];
        foreach (($xmlObject->url ?? []) as $url) {
            $entries[(string) $url->loc] = [(string) $url->changefreq, (string) $url->priority];
        }

        self::assertSame(['yearly', '0.4'], $entries['http://bumdes-sukamaju.test/terms'] ?? null);
        self::assertSame(['yearly', '0.4'], $entries['http://bumdes-sukamaju.test/privacy'] ?? null);
    }

    public function test_sitemap_lists_legal_urls_on_platform_hosts_as_well(): void
    {
        $this->clearTenantTestContext();

        $xml = (string) $this->get('http://localhost/sitemap.xml')->assertOk()->getContent();

        self::assertStringContainsString('http://localhost/terms', $xml);
        self::assertStringContainsString('http://localhost/privacy', $xml);
    }

    /**
     * @return array<int, string>
     */
    private function sectionTitles(string $url): array
    {
        $document = [];
        $this->get($url)->assertOk()->assertInertia(function ($page) use (&$document): void {
            $document = (array) $page->toArray()['props']['document'];
        });

        return array_column((array) $document['sections'], 'title');
    }

    /**
     * @return array<string, mixed>
     */
    private function firstJsonLd(string $content): array
    {
        $matches = [];
        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $content, $matches);
        self::assertArrayHasKey(1, $matches, 'Response is missing its JSON-LD block.');

        return json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
    }

    private function activateTenantDomain(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();
    }
}

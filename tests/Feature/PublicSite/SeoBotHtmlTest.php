<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use App\Tenancy\Services\PublicSiteResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsTenantTestDatabase;
use Tests\TestCase;

final class SeoBotHtmlTest extends TestCase
{
    use BuildsTenantTestDatabase;

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

    public function test_googlebot_receives_server_rendered_post_content_and_json_ld(): void
    {
        $this->seedPublishedPost();

        $response = $this->get('http://bumdes-sukamaju.test/berita/laporan-tahunan', [
            'User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
        ]);

        $response->assertOk();
        $content = $response->getContent();
        self::assertStringContainsString('Isi laporan.', $content);
        self::assertStringNotContainsString('id="app"', $content);

        $matches = [];
        preg_match('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $content, $matches);
        self::assertArrayHasKey(1, $matches);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('NewsArticle', $schema['@type']);
        self::assertSame('Laporan Tahunan', $schema['headline']);
    }

    public function test_normal_browser_receives_inertia_spa(): void
    {
        $this->seedPublishedPost();

        $response = $this->get('http://bumdes-sukamaju.test/berita', [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ]);

        $response->assertOk();
        self::assertStringContainsString('id="app"', (string) $response->getContent());
        self::assertStringNotContainsString('Berita terbaru', (string) $response->getContent());
    }

    public function test_sitemap_contains_lastmod_changefreq_and_priority(): void
    {
        $updatedAt = Carbon::now()->subDays(2)->toIso8601String();
        DB::connection('tenant')->table('site_posts')->insert($this->postRow([
            'slug' => 'laporan-tahunan',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
            'updated_at' => $updatedAt,
        ]));

        $response = $this->get('http://bumdes-sukamaju.test/sitemap.xml');
        $response->assertOk();
        $xml = (string) $response->getContent();
        simplexml_load_string($xml);
        self::assertStringContainsString('<lastmod>'.$updatedAt.'</lastmod>', $xml);
        self::assertStringContainsString('<changefreq>daily</changefreq>', $xml);
        self::assertStringContainsString('<priority>0.8</priority>', $xml);
    }

    public function test_json_ld_appears_on_home_without_client_side_execution(): void
    {
        $response = $this->get('http://bumdes-sukamaju.test/', [
            'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64)',
        ]);

        $response->assertOk();
        $content = (string) $response->getContent();
        $matches = [];
        preg_match_all('#<script type="application/ld\+json"[^>]*>(.*?)</script>#s', $content, $matches);
        $types = array_column(array_map(
            fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR),
            $matches[1],
        ), '@type');

        self::assertSame(['Organization', 'WebSite'], $types);
    }

    private function activateTenantDomain(): void
    {
        $this->testTenant->forceFill([
            'metadata' => ['domains' => ['bumdes-sukamaju.test']],
        ])->save();
        app(PublicSiteResolver::class)->flush();
    }

    private function seedPublishedPost(): void
    {
        DB::connection('tenant')->table('site_posts')->insert($this->postRow([
            'slug' => 'laporan-tahunan',
            'title' => 'Laporan Tahunan',
            'excerpt' => 'Ringkasan laporan.',
            'content' => '<p>Isi laporan.</p>',
            'status' => 'published',
            'published_at' => Carbon::now()->subDay(),
            'updated_at' => Carbon::now()->subDay(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function postRow(array $overrides = []): array
    {
        return [
            'tenant_id' => $this->testTenant->row_id,
            'id' => 1,
            'slug' => 'contoh',
            'title' => 'Contoh',
            'excerpt' => null,
            'content' => '<p>Isi.</p>',
            'cover_image_path' => null,
            'status' => 'draft',
            'published_at' => null,
            'author_name' => null,
            'meta_description' => null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'deleted_at' => null,
            ...$overrides,
        ];
    }
}

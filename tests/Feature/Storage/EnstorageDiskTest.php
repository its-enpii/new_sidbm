<?php

declare(strict_types=1);

namespace Tests\Feature\Storage;

use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class EnstorageDiskTest extends TestCase
{
    public function test_enstorage_disk_is_configured_as_flysystem_s3_driver(): void
    {
        $disk = config('filesystems.disks.enstorage');

        $this->assertIsArray($disk, 'The enstorage disk must be configured in config/filesystems.php.');
        $this->assertSame('s3', $disk['driver'] ?? null);
        $this->assertTrue((bool) ($disk['use_path_style_endpoint'] ?? false));
        $this->assertNotEmpty($disk['bucket'] ?? null);
        $this->assertNotEmpty($disk['endpoint'] ?? null);
    }

    public function test_enstorage_disk_resolves_to_flysystem_s3_adapter(): void
    {
        $adapter = Storage::disk('enstorage');

        $this->assertInstanceOf(AwsS3V3Adapter::class, $adapter);
    }

    public function test_storage_fake_enstorage_can_put_get_and_delete(): void
    {
        Storage::fake('enstorage');

        Storage::disk('enstorage')->put('site/posts/1/cover.jpg', 'binary-image-contents');

        Storage::disk('enstorage')->assertExists('site/posts/1/cover.jpg');
        $this->assertSame('binary-image-contents', Storage::disk('enstorage')->get('site/posts/1/cover.jpg'));

        Storage::disk('enstorage')->delete('site/posts/1/cover.jpg');

        Storage::disk('enstorage')->assertMissing('site/posts/1/cover.jpg');
    }

    public function test_upload_disk_follows_configured_default(): void
    {
        config()->set('filesystems.upload_disk', 'enstorage');

        $this->assertSame('enstorage', config('filesystems.upload_disk'));

        Storage::fake('enstorage');
        Storage::disk((string) config('filesystems.upload_disk'))->put('thumb.jpg', 'x');

        Storage::disk('enstorage')->assertExists('thumb.jpg');
    }
}

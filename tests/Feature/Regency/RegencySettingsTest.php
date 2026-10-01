<?php

declare(strict_types=1);

namespace Tests\Feature\Regency;

use App\Models\User;
use App\Services\PlatformSettingService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class RegencySettingsTest extends TestCase
{
    private User $supervisor;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

        Artisan::call('migrate:fresh', [
            '--database' => 'platform',
            '--path' => 'database/migrations/platform',
            '--force' => true,
        ]);

        $this->supervisor = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Supervisor Kabupaten',
            'email' => 'supervisor@example.test',
            'username' => 'supervisor_kab',
            'password' => 'password',
            'status' => 'active',
            'is_regency_user' => true,
            'regency_code' => '3301',
            'regency_name' => 'Cilacap',
        ]);

        $this->regularUser = User::query()->create([
            'public_id' => (string) Str::ulid(),
            'name' => 'Pengguna Biasa',
            'email' => 'biasa@example.test',
            'username' => 'user_biasa',
            'password' => 'password',
            'status' => 'active',
            'is_regency_user' => false,
        ]);
    }

    public function test_regency_user_can_view_settings_page(): void
    {
        $this->actingAs($this->supervisor)
            ->get('/regency/settings')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Regency/Settings')
                ->where('regency_code', '3301'));
    }

    public function test_regency_user_can_upload_and_delete_logo(): void
    {
        Storage::fake(config('filesystems.upload_disk', 'public'));

        $file = UploadedFile::fake()->image('pemkab.png', 200, 200);

        $this->actingAs($this->supervisor)
            ->post('/regency/settings/logo', ['logo' => $file])
            ->assertRedirect(route('regency.settings'));

        $path = app(PlatformSettingService::class)->get('regency.3301.logo_path');
        $this->assertNotNull($path);

        Storage::disk(config('filesystems.upload_disk', 'public'))->assertExists($path);

        $this->actingAs($this->supervisor)
            ->delete('/regency/settings/logo')
            ->assertRedirect(route('regency.settings'));

        $this->assertNull(app(PlatformSettingService::class)->get('regency.3301.logo_path'));
        Storage::disk(config('filesystems.upload_disk', 'public'))->assertMissing($path);
    }

    public function test_regency_user_can_update_identity(): void
    {
        $this->actingAs($this->supervisor)
            ->put('/regency/settings/identity', [
                'official_name' => 'Dinas PMD Kabupaten Cilacap',
                'address' => 'Jl. Jenderal Sudirman No. 12',
            ])
            ->assertRedirect(route('regency.settings'));

        $this->assertSame('Dinas PMD Kabupaten Cilacap', app(PlatformSettingService::class)->get('regency.3301.official_name'));
        $this->assertSame('Jl. Jenderal Sudirman No. 12', app(PlatformSettingService::class)->get('regency.3301.address'));
    }

    public function test_regular_user_cannot_access_regency_settings(): void
    {
        $this->actingAs($this->regularUser)
            ->get('/regency/settings')
            ->assertRedirect();
    }
}

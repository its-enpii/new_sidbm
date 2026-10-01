<?php

declare(strict_types=1);

namespace App\Http\Controllers\Regency;

use App\Http\Requests\Regency\RegencyIdentityRequest;
use App\Http\Requests\Regency\RegencyLogoUploadRequest;
use App\Services\PlatformSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

final class RegencySettingsController
{
    public function __construct(
        private readonly PlatformSettingService $platformSettings,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $regencyCode = (string) ($user?->regency_code ?? '');

        $logoPath = $this->platformSettings->get("regency.{$regencyCode}.logo_path");
        $logoUrl = $logoPath ? asset('storage/'.ltrim((string) $logoPath, '/')) : null;
        $officialName = $this->platformSettings->get("regency.{$regencyCode}.official_name", '');
        $address = $this->platformSettings->get("regency.{$regencyCode}.address", '');

        return Inertia::render('Regency/Settings', [
            'regency_code' => $regencyCode,
            'regency_name' => $user?->regency_name ?? 'Kabupaten',
            'logo_url' => $logoUrl,
            'official_name' => $officialName,
            'address' => $address,
        ]);
    }

    public function updateLogo(RegencyLogoUploadRequest $request): RedirectResponse
    {
        $user = $request->user();
        $regencyCode = (string) ($user->regency_code ?: 'default');

        $file = $request->file('logo');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'png');

        $this->deleteStoredLogo($regencyCode);

        $path = $file->storeAs("regencies/{$regencyCode}", 'logo.'.$ext, $this->uploadDisk());

        $this->platformSettings->set("regency.{$regencyCode}.logo_path", $path);

        return redirect()->route('regency.settings')->with('success', 'Logo kabupaten berhasil diperbarui.');
    }

    public function destroyLogo(Request $request): RedirectResponse
    {
        $user = $request->user();
        $regencyCode = (string) ($user->regency_code ?: 'default');

        $this->deleteStoredLogo($regencyCode);

        $this->platformSettings->set("regency.{$regencyCode}.logo_path", null);

        return redirect()->route('regency.settings')->with('success', 'Logo kabupaten berhasil dihapus.');
    }

    public function updateIdentity(RegencyIdentityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $regencyCode = (string) ($user->regency_code ?: 'default');

        $this->platformSettings->set(
            "regency.{$regencyCode}.official_name",
            (string) ($request->validated('official_name') ?? ''),
        );
        $this->platformSettings->set(
            "regency.{$regencyCode}.address",
            (string) ($request->validated('address') ?? ''),
        );

        return redirect()->route('regency.settings')->with('success', 'Identitas instansi berhasil disimpan.');
    }

    private function deleteStoredLogo(string $regencyCode): void
    {
        $existing = $this->platformSettings->get("regency.{$regencyCode}.logo_path");

        if (! is_string($existing) || $existing === '') {
            return;
        }

        $disk = Storage::disk($this->uploadDisk());
        if ($disk->exists($existing)) {
            $disk->delete($existing);
        }
    }

    private function uploadDisk(): string
    {
        return (string) config('filesystems.upload_disk', config('filesystems.default', 'public'));
    }
}

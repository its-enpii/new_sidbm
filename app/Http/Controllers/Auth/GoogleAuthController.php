<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Google OAuth2 entry points: public "Lanjutkan dengan Google" login plus
 * self-service linking / unlinking and email notification preferences from the
 * profile page.
 */
final class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleAuthService $service): RedirectResponse
    {
        if (! $service->isConfigured()) {
            return redirect()
                ->route('login')
                ->with('error', 'Integrasi Google belum dikonfigurasi.');
        }

        $action = $request->query('action') === 'link' ? 'link' : 'login';

        if ($action === 'link') {
            $user = $request->user();
            if ($user === null) {
                return redirect()->route('login');
            }

            return redirect()->away($service->getAuthorizationUrl('link', (int) $user->row_id));
        }

        return redirect()->away($service->getAuthorizationUrl('login'));
    }

    public function callback(Request $request, GoogleAuthService $service): RedirectResponse
    {
        $action = $request->query('action') === 'link' ? 'link' : 'login';

        try {
            $google = $service->handleCallback($request);
            $action = $google['action'] === 'link' ? 'link' : 'login';
        } catch (Throwable $e) {
            return $this->failedRedirect($action, $e->getMessage());
        }

        return $action === 'link'
            ? $this->handleLink($request, $google)
            : $this->handleLogin($request, $google);
    }

    public function unlink(Request $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'google_id' => null,
            'google_email' => null,
            'google_avatar' => null,
            'google_linked_at' => null,
        ])->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'account'])
            ->with('success', 'Hubungan akun Google berhasil diputuskan.');
    }

    public function updateNotificationSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'billing' => ['sometimes', 'boolean'],
            'announcements' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $current = is_array($user->email_notifications) ? $user->email_notifications : [];

        $user->email_notifications = [
            'billing' => array_key_exists('billing', $validated)
                ? (bool) $validated['billing']
                : (bool) ($current['billing'] ?? true),
            'announcements' => array_key_exists('announcements', $validated)
                ? (bool) $validated['announcements']
                : (bool) ($current['announcements'] ?? true),
        ];
        $user->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'account'])
            ->with('success', 'Preferensi notifikasi email berhasil diperbarui.');
    }

    /**
     * @param  array{google_id: string, email: string, name: string, avatar: ?string, action: string, user_id: ?int}  $google
     */
    private function handleLink(Request $request, array $google): RedirectResponse
    {
        $user = $request->user();
        if ($user === null) {
            return redirect()->route('login');
        }

        $taken = User::query()
            ->where('google_id', $google['google_id'])
            ->where('row_id', '!=', $user->row_id)
            ->exists();

        if ($taken) {
            return redirect()
                ->route('profile.edit', ['tab' => 'account'])
                ->with('error', 'Akun Google ini sudah terhubung dengan akun lain.');
        }

        $user->forceFill([
            'google_id' => $google['google_id'],
            'google_email' => $google['email'],
            'google_avatar' => $google['avatar'],
            'google_linked_at' => now(),
        ]);

        if (empty($user->email)) {
            $user->email = $google['email'];
        }

        $user->save();

        return redirect()
            ->route('profile.edit', ['tab' => 'account'])
            ->with('success', "Akun Google ({$google['email']}) berhasil dihubungkan.");
    }

    /**
     * @param  array{google_id: string, email: string, name: string, avatar: ?string, action: string, user_id: ?int}  $google
     */
    private function handleLogin(Request $request, array $google): RedirectResponse
    {
        $user = User::query()->where('google_id', $google['google_id'])->first();

        if ($user === null) {
            return redirect()
                ->route('login')
                ->with('error', 'Akun Google ('.$google['email'].') belum terhubung dengan akun pengguna manapun. Silakan masuk dengan username/email dan kata sandi Anda, lalu hubungkan akun Google di halaman Profil.');
        }

        if ($user->status !== 'active') {
            return redirect()
                ->route('login')
                ->with('error', 'Akun Anda dinonaktifkan.');
        }

        return $this->login($request, $user);
    }

    private function login(Request $request, User $user): RedirectResponse
    {
        if ($user->tenant_id === null && ! $user->is_superadmin && ! $user->isRegencyUser()) {
            $membership = $user->memberships()->where('status', 'active')->first();
            $user->forceFill(['tenant_id' => $membership?->tenant_id])->save();
        }

        $user->forceFill(['last_login_at' => now()])->save();
        Auth::login($user, true);
        $request->session()->regenerate();

        if ($user->is_superadmin === true) {
            $request->session()->forget('url.intended');

            return redirect()->route('admin.dashboard');
        }

        if ($user->isRegencyUser()) {
            $request->session()->forget('url.intended');

            return redirect()->route('regency.dashboard');
        }

        return redirect()->to($this->intendedUrl($request));
    }

    private function intendedUrl(Request $request): string
    {
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended)) {
            return route('dashboard');
        }

        $path = parse_url($intended, PHP_URL_PATH);
        if (str_contains((string) $path, '/admin') || str_contains((string) $path, '/regency')) {
            return route('dashboard');
        }

        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https' || str_starts_with((string) config('app.url'), 'https://')) {
            $intended = preg_replace('/^http:/i', 'https:', $intended);
        }

        return $intended;
    }

    private function failedRedirect(string $action, string $message): RedirectResponse
    {
        if ($action === 'link') {
            return redirect()
                ->route('profile.edit', ['tab' => 'account'])
                ->with('error', $message);
        }

        return redirect()->route('login')->with('error', $message);
    }
}

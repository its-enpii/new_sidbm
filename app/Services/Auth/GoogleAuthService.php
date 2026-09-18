<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Google OAuth2 bridge built on Laravel's native HTTP client.
 *
 * The opaque `state` value carries the intended action (login vs. link) plus a
 * timestamp and nonce, encrypted with the app key so it cannot be tampered with
 * or replayed beyond the 15 minute window.
 */
final class GoogleAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    private const STATE_TTL_SECONDS = 900;

    /**
     * @return array{google_id: string, email: string, name: string, avatar: ?string, action: string, user_id: ?int}
     */
    public function handleCallback(Request $request): array
    {
        $error = $request->query('error');
        if (is_string($error) && $error !== '') {
            throw new RuntimeException('Google membatalkan permintaan: '.$error);
        }

        $state = $this->decodeState((string) $request->query('state', ''));
        $code = (string) $request->query('code', '');
        if ($code === '') {
            throw new RuntimeException('Kode otorisasi Google tidak ditemukan.');
        }

        $tokens = $this->exchangeCode($code);
        if (! isset($tokens['access_token']) || ! is_string($tokens['access_token'])) {
            throw new RuntimeException('Google tidak mengembalikan access token.');
        }

        $profile = $this->fetchUserinfo($tokens['access_token']);
        if (! isset($profile['sub']) || ! is_string($profile['sub'])) {
            throw new RuntimeException('Profil Google tidak lengkap (subject ID hilang).');
        }

        return [
            'google_id' => (string) $profile['sub'],
            'email' => (string) ($profile['email'] ?? ''),
            'name' => (string) ($profile['name'] ?? ''),
            'avatar' => isset($profile['picture']) && is_string($profile['picture']) ? $profile['picture'] : null,
            'action' => $state['action'],
            'user_id' => isset($state['user_id']) ? (int) $state['user_id'] : null,
        ];
    }

    public function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id')) && ! empty(config('services.google.client_secret'));
    }

    public function getAuthorizationUrl(string $action = 'login', ?int $userId = null): string
    {
        $query = http_build_query([
            'client_id' => (string) config('services.google.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'state' => $this->encodeState($action, $userId),
            'prompt' => 'select_account',
        ]);

        return self::AUTH_URL.'?'.$query;
    }

    public function redirectUri(): string
    {
        return (string) config('services.google.redirect_uri');
    }

    /**
     * @return array{action: string, user_id: ?int, ts: int, nonce: string}
     */
    private function decodeState(string $state): array
    {
        if ($state === '') {
            throw new RuntimeException('State OAuth tidak ditemukan.');
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('State OAuth tidak valid atau telah diubah.');
        }

        if (! is_array($payload) || ! isset($payload['action'], $payload['ts'], $payload['nonce'])) {
            throw new RuntimeException('State OAuth tidak valid.');
        }

        $age = time() - (int) $payload['ts'];
        if ($age > self::STATE_TTL_SECONDS || $age < -60) {
            throw new RuntimeException('Sesi otorisasi Google telah kedaluwarsa. Silakan coba lagi.');
        }

        return [
            'action' => (string) $payload['action'],
            'user_id' => isset($payload['user_id']) ? (int) $payload['user_id'] : null,
            'ts' => (int) $payload['ts'],
            'nonce' => (string) $payload['nonce'],
        ];
    }

    private function encodeState(string $action, ?int $userId): string
    {
        $payload = [
            'action' => in_array($action, ['login', 'link'], true) ? $action : 'login',
            'user_id' => $userId,
            'ts' => time(),
            'nonce' => Str::random(16),
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code): array
    {
        $response = Http::asForm()
            ->timeout(20)
            ->post(self::TOKEN_URL, [
                'code' => $code,
                'client_id' => (string) config('services.google.client_id'),
                'client_secret' => (string) config('services.google.client_secret'),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal menukar kode otorisasi Google dengan token.');
        }

        return $response->json();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchUserinfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)
            ->timeout(20)
            ->get(self::USERINFO_URL);

        if (! $response->successful()) {
            throw new RuntimeException('Gagal mengambil profil Google.');
        }

        return $response->json();
    }
}

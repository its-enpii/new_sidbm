<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class RegionalCodeApi
{
    public function provinces(): array
    {
        return $this->get('/provinces', 2, '');
    }

    public function regencies(string $provinceCode): array
    {
        $this->assertCode($provinceCode, 2);

        return $this->get('/regencies/'.rawurlencode($provinceCode), 4, $provinceCode);
    }

    public function districts(string $regencyCode): array
    {
        $this->assertCode($regencyCode, 4);

        return $this->get('/districts/'.rawurlencode($regencyCode), 6, $regencyCode);
    }

    public function villages(string $districtCode): array
    {
        $this->assertCode($districtCode, 6);
        $villages = $this->get('/villages/'.rawurlencode($districtCode), 10, $districtCode);

        foreach ($villages as $village) {
            if (! str_starts_with($village['code'], $districtCode)) {
                throw new RuntimeException('Regional API returned a village outside the requested district.');
            }
        }

        return $villages;
    }

    private function get(string $path, int $codeLength, string $parentCode): array
    {
        $cacheKey = 'regional-code:'.md5($path);
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) return $cached;

        $response = Http::baseUrl(rtrim((string) config('services.regional_api.base_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('services.regional_api.timeout', 10))
            ->retry(2, 200)
            ->get($path);

        if (! $response->successful()) {
            throw new RuntimeException("Regional API request failed [{$response->status()}].");
        }

        $payload = $response->json();
        $data = is_array($payload) ? ($payload['data'] ?? null) : null;

        if (! is_array($data) || $data === []) {
            throw new RuntimeException('Regional API returned an invalid or empty data set.');
        }

        $result = array_map(function (mixed $item) use ($codeLength, $parentCode): array {
            if (! is_array($item) || ! isset($item['code'], $item['name'])) {
                throw new RuntimeException('Regional API returned an invalid region item.');
            }

            $code = (string) $item['code'];
            $name = trim((string) $item['name']);

            if (strlen($code) !== $codeLength || ! ctype_digit($code) || strlen($name) < 1) {
                throw new RuntimeException('Regional API returned an invalid region code or name.');
            }

            if ($parentCode !== '' && ! str_starts_with($code, $parentCode)) {
                throw new RuntimeException('Regional API returned an invalid region hierarchy.');
            }

            return ['code' => $code, 'name' => $name];
        }, $data);

        Cache::put($cacheKey, $result, now()->addDay());

        return $result;
    }

    private function assertCode(string $code, int $length): void
    {
        if (strlen($code) !== $length || ! ctype_digit($code)) {
            throw new RuntimeException("Regional code must contain {$length} digits.");
        }
    }
}

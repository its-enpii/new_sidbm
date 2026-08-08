<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Tools;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound HTTP with SSRF protections for agent web tools.
 */
final class SafeHttpClient
{
    /**
     * @param  list<string>  $allowlist  host or .suffix; empty = all public hosts
     * @return array{ok:bool, status?:int, final_url?:string, body?:string, error?:string}
     */
    public function get(
        string $url,
        array $allowlist = [],
        int $timeout = 15,
        int $maxBytes = 500_000,
        string $userAgent = 'EnpiiAssistant/1.0',
    ): array {
        try {
            $normalized = $this->assertSafeUrl($url, $allowlist);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }

        try {
            $response = Http::timeout($timeout)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,application/json;q=0.8,*/*;q=0.7',
                ])
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 5,
                        'track_redirects' => true,
                    ],
                ])
                ->get($normalized);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'request_failed: '.$e->getMessage()];
        }

        $final = $normalized;
        $history = $response->handlerStats()['redirect_url'] ?? null;
        if (is_string($history) && $history !== '') {
            $final = $history;
        } elseif (method_exists($response, 'effectiveUri') && $response->effectiveUri()) {
            $final = (string) $response->effectiveUri();
        }
        try {
            $this->assertSafeUrl($final, $allowlist);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => 'redirect_blocked: '.$e->getMessage()];
        }

        $body = $response->body();
        if (strlen($body) > $maxBytes) {
            $body = substr($body, 0, $maxBytes);
        }

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'final_url' => $final,
            'body' => $body,
            'error' => $response->successful() ? null : 'http_'.$response->status(),
        ];
    }

    /**
     * @param  list<string>  $allowlist
     */
    public function assertSafeUrl(string $url, array $allowlist = []): string
    {
        $url = trim($url);
        if ($url === '') {
            throw new RuntimeException('empty_url');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('invalid_url');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('scheme_not_allowed');
        }

        $host = strtolower((string) $parts['host']);
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            throw new RuntimeException('host_blocked');
        }

        if ($allowlist !== [] && ! $this->hostAllowed($host, $allowlist)) {
            throw new RuntimeException('host_not_in_allowlist');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isBlockedIp($host)) {
                throw new RuntimeException('ip_blocked');
            }
        } else {
            $ips = gethostbynamel($host) ?: [];
            if ($ips === []) {
                $packed = @dns_get_record($host, DNS_AAAA);
                if (is_array($packed)) {
                    foreach ($packed as $row) {
                        if (! empty($row['ipv6'])) {
                            $ips[] = $row['ipv6'];
                        }
                    }
                }
            }
            if ($ips === []) {
                throw new RuntimeException('dns_failed');
            }
            foreach ($ips as $ip) {
                if ($this->isBlockedIp($ip)) {
                    throw new RuntimeException('resolved_ip_blocked');
                }
            }
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array((int) $port, [80, 443], true)) {
            throw new RuntimeException('port_not_allowed');
        }

        return $url;
    }

    /**
     * @param  list<string>  $allowlist
     */
    private function hostAllowed(string $host, array $allowlist): bool
    {
        foreach ($allowlist as $rule) {
            $rule = strtolower(trim($rule));
            if ($rule === '') {
                continue;
            }
            if (str_starts_with($rule, '.')) {
                if ($host === substr($rule, 1) || str_ends_with($host, $rule)) {
                    return true;
                }
            } elseif ($host === $rule || str_ends_with($host, '.'.$rule)) {
                return true;
            }
        }

        return false;
    }

    private function isBlockedIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }
            $ranges = [
                ['0.0.0.0', '0.255.255.255'],
                ['10.0.0.0', '10.255.255.255'],
                ['100.64.0.0', '100.127.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['172.16.0.0', '172.31.255.255'],
                ['192.0.0.0', '192.0.0.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['198.18.0.0', '198.19.255.255'],
                ['224.0.0.0', '255.255.255.255'],
            ];
            foreach ($ranges as [$a, $b]) {
                if ($long >= ip2long($a) && $long <= ip2long($b)) {
                    return true;
                }
            }

            return false;
        }

        $ip = strtolower($ip);
        if ($ip === '::1' || $ip === '::' || str_starts_with($ip, 'fe80:') || str_starts_with($ip, 'fc') || str_starts_with($ip, 'fd')) {
            return true;
        }

        return false;
    }
}

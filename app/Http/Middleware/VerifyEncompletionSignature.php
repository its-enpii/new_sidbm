<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies inbound Kategori-B tool calls from encompletion.
 *
 * Headers:
 *   X-Encompletion-Signature  = HMAC-SHA256(ts + '.' + rawBody, key_hash)
 *   X-Encompletion-Timestamp  = ms epoch string
 *   X-Encompletion-Key-Hash   = sha256(tenant_api_key plaintext)  [optional cross-check]
 *
 * Secret is sha256(ENCOMPLETION_TENANT_API_KEY) — matches tool-executor.js.
 */
final class VerifyEncompletionSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $plaintext = (string) config('encompletion.tenant_api_key', '');
        if ($plaintext === '') {
            return response()->json(['error' => 'encompletion not configured'], 503);
        }

        $expectedHash = hash('sha256', $plaintext);
        $sig = (string) $request->header('X-Encompletion-Signature', '');
        $ts = (string) $request->header('X-Encompletion-Timestamp', '');
        $keyHash = (string) $request->header('X-Encompletion-Key-Hash', '');

        if ($sig === '' || $ts === '' || ! ctype_digit($ts)) {
            return response()->json(['error' => 'missing signature headers'], 401);
        }

        if ($keyHash !== '' && ! hash_equals($expectedHash, $keyHash)) {
            return response()->json(['error' => 'key hash mismatch'], 401);
        }

        $skew = (int) config('encompletion.signature_max_skew_ms', 300_000);
        $now = (int) floor(microtime(true) * 1000);
        if (abs($now - (int) $ts) > $skew) {
            return response()->json(['error' => 'signature timestamp skew'], 401);
        }

        $raw = $request->getContent();
        $expected = hash_hmac('sha256', $ts.'.'.$raw, $expectedHash);

        if (! hash_equals($expected, $sig)) {
            return response()->json(['error' => 'invalid signature'], 401);
        }

        return $next($request);
    }
}

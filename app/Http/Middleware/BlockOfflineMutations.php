<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class BlockOfflineMutations
{
    /**
     * Route patterns allowed even in offline mode (e.g., local login, logout, ping, sync).
     *
     * @var array<int, string>
     */
    private const ALLOWED_PATTERNS = [
        'login*',
        'logout*',
        'desktop/sync*',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Safe HTTP methods (GET, HEAD, OPTIONS) are always allowed in Read-Only mode
        if ($this->isSafeMethod($request)) {
            return $next($request);
        }

        // Whitelisted mutations (auth login, local session logout, sync trigger)
        foreach (self::ALLOWED_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return $next($request);
            }
        }

        // Determine if offline mutation guard is triggered
        if ($this->isOffline(request: $request)) {
            $message = 'Aplikasi dalam mode offline (Hanya Baca). Penambahan, perubahan, atau penghapusan data dinonaktifkan.';

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'code' => 'OFFLINE_READ_ONLY_GUARD',
                    'message' => $message,
                ], 403);
            }

            return back()->with('error', $message);
        }

        return $next($request);
    }

    private function isSafeMethod(Request $request): bool
    {
        return in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function isOffline(Request $request): bool
    {
        // Explicit offline simulation header from frontend / client
        if ($request->header('X-Client-Offline') === 'true' || $request->query('simulate_offline') === '1') {
            return true;
        }

        // When in desktop mode and explicitly marked offline via config/session
        if ((bool) config('desktop.enabled', false) && (bool) config('desktop.offline', false)) {
            return true;
        }

        return false;
    }
}

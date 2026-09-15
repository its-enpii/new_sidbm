<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Features\Services\FeatureAccessService;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class EnsureFeatureEnabled
{
    public function __construct(
        private FeatureAccessService $features,
        private TenantContext $context,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenantId = $this->context->isInitialized() ? $this->context->id() : null;

        if ($this->features->enabled($feature, $tenantId)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*') || $request->routeIs('assistant.*')) {
            $requestUrl = route('assistant.access-request', absolute: false);

            return response()->json([
                'message' => 'Fitur AI belum aktif untuk usaha ini.',
                'status' => 'feature_disabled',
                'feature' => $feature,
                'request_url' => $requestUrl,
            ], 403);
        }

        return back()->with('error', 'Fitur AI belum aktif untuk usaha ini. Ajukan langganan ke administrator Anda.');
    }
}

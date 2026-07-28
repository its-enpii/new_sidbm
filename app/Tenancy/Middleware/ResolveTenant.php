<?php

declare(strict_types=1);

namespace App\Tenancy\Middleware;

use App\Tenancy\Services\ShardConnectionManager;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolveTenant
{
    public function __construct(
        private TenantResolver $resolver,
        private TenantContext $context,
        private ShardConnectionManager $connectionManager,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $parameter = (string) config('tenancy.route_parameter', 'tenant');
        $code = $request->route($parameter);

        if (! is_string($code) || trim($code) === '') {
            // Server-to-server tool callbacks (encompletion) may send X-Tenant-Code
            // when multiple tenants share one host. Gated by tenancy.allow_header.
            if (config('tenancy.allow_header')) {
                $headerName = (string) config('tenancy.header', 'X-Tenant-Code');
                $headerCode = $request->header($headerName);
                if (is_string($headerCode) && trim($headerCode) !== '') {
                    $code = trim($headerCode);
                }
            }
        }

        $tenant = is_string($code) && trim($code) !== ''
            ? $this->resolver->resolveByCode(trim($code), $request->user())
            : $this->resolver->resolveByHost($request->getHost(), $request->user());
        $placement = $tenant->placement;
        $shard = $placement?->shard;

        if ($placement === null || $shard === null) {
            throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException('Tenant placement is incomplete.');
        }

        $this->connectionManager->connect($shard);
        $this->context->initialize($tenant, $placement, $shard);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
            $this->connectionManager->disconnect();
        }
    }
}

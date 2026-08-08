<?php

declare(strict_types=1);

namespace Enpii\Assistant\Contracts;

/**
 * Resolves the current tenant id from the host application.
 *
 * Implementation must be cheap (called per tool use, per message).
 * The host app typically delegates to its TenantContext service:
 *
 *   $this->app->bind(TenantResolver::class, fn () => app(TenantContext::class)->id());
 */
interface TenantResolver
{
    /**
     * @return string tenant id (uuid or integer string — up to host app)
     */
    public function resolve(): string;
}

<?php

declare(strict_types=1);

namespace Enpii\Assistant\Contracts;

/**
 * Runtime context carried into every tool handler invocation.
 *
 * $actor is host-app-specific — typically an Eloquent User model — and is
 * passed so handlers can implement permission checks, impersonation, etc.
 */
final class ToolContext
{
    /**
     * @param  mixed  $actor  host-app actor (e.g. Auth::user()); opaque to package
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $externalUserId,
        public readonly mixed $actor = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Enpii\Assistant\Contracts;

/**
 * Resolves the current assistant session context from the host application.
 *
 * Called at the start of every chat turn to determine which tenant /
 * user / persona apply.
 */
interface SessionResolver
{
    /**
     * @return array{
     *     tenant_id: string,
     *     external_user_id: string,
     *     persona_slug: ?string,
     * }
     */
    public function resolve(): array;
}

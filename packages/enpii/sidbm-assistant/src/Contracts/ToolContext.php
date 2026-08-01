<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Contracts;

/**
 * Read-only context the host passes to each tool invocation.
 */
final class ToolContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly ?string $tenantCode = null,
    ) {
    }
}
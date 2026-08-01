<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Contracts;

/**
 * Tenant-scoped tool registry. Host applications supply their own
 * implementation so domain tools can be wired up without the package
 * knowing about the host's internal model layer.
 */
interface ToolRegistry
{
    /**
     * @return list<AssistantToolHandler>
     */
    public function tools(): array;

    public function find(string $name): ?AssistantToolHandler;
}
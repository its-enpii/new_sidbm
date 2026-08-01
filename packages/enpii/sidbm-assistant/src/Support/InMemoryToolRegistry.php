<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Support;

use Enpii\SidbmAssistant\Contracts\AssistantToolHandler;
use Enpii\SidbmAssistant\Contracts\ToolRegistry;

final class InMemoryToolRegistry implements ToolRegistry
{
    /** @var array<string, AssistantToolHandler> */
    private array $byName = [];

    public function register(AssistantToolHandler $handler): void
    {
        $this->byName[$handler->toolName()] = $handler;
    }

    public function find(string $name): ?AssistantToolHandler
    {
        return $this->byName[$name] ?? null;
    }

    /** @return list<AssistantToolHandler> */
    public function tools(): array
    {
        return array_values($this->byName);
    }
}
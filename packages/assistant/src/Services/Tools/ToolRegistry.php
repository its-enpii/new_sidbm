<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Tools;

use Enpii\Assistant\Contracts\ToolHandler;

/**
 * Maps tool names → concrete ToolHandler instances.
 *
 * Host app registers handlers via ToolRegistry::registerMany() in their
 * AppServiceProvider::register() — typically by resolving each handler
 * class out of the container so its own dependencies (e.g. AssistantToolService)
 * are injected automatically.
 */
final class ToolRegistry
{
    /** @var array<string, ToolHandler> */
    private array $handlers = [];

    public function register(ToolHandler $handler): void
    {
        $this->handlers[$handler->name()] = $handler;
    }

    /**
     * @param  iterable<ToolHandler>  $handlers
     */
    public function registerMany(iterable $handlers): void
    {
        foreach ($handlers as $h) {
            $this->register($h);
        }
    }

    public function resolve(string $name): ?ToolHandler
    {
        return $this->handlers[$name] ?? null;
    }

    /**
     * @return list<ToolHandler>
     */
    public function all(): array
    {
        return array_values($this->handlers);
    }

    public function count(): int
    {
        return count($this->handlers);
    }
}

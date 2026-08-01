<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Services;

use Enpii\SidbmAssistant\Contracts\AssistantActor;
use Enpii\SidbmAssistant\Contracts\AssistantToolHandler;
use Enpii\SidbmAssistant\Contracts\ToolContext;
use Enpii\SidbmAssistant\Contracts\ToolRegistry;
use InvalidArgumentException;
use RuntimeException;

final class ToolDispatcher
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function dispatch(string $tool, array $params, AssistantActor $actor, ToolContext $context): array
    {
        $handler = $this->registry->find($tool);
        if ($handler === null) {
            throw new RuntimeException("Unknown tool: {$tool}");
        }

        if ($handler->requiredPermission() !== '') {
            if (! $actor->hasPermission($handler->requiredPermission())) {
                throw new RuntimeException("Permission denied: {$handler->requiredPermission()}");
            }
        }

        return $handler->execute($params, $actor, $context);
    }

    /**
     * Dump tool definitions for orchestrator seed (read by artisan command).
     *
     * @return list<array<string, mixed>>
     */
    public function exportDefinitions(string $adapterBaseUrl): array
    {
        $out = [];
        foreach ($this->registry->tools() as $tool) {
            $out[] = $this->definition($tool, $adapterBaseUrl);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(AssistantToolHandler $tool, string $adapterBaseUrl): array
    {
        return [
            'name' => $tool->toolName(),
            'description' => $tool->description(),
            'requires_confirmation' => $tool->requiresConfirmation(),
            'endpoint_url' => rtrim($adapterBaseUrl, '/').'/'.ltrim(config('assistant.routes.tools_prefix', 'api/assistant/tools'), '/').'/'.$tool->toolName(),
            'json_schema' => $tool->inputSchema(),
        ];
    }
}
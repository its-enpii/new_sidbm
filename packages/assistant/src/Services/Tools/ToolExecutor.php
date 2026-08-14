<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Tools;

use Enpii\Assistant\Contracts\ToolContext;
use Enpii\Assistant\Contracts\ToolHandler;
use Enpii\Assistant\Models\Tool;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * In-process tool executor. Replaces the legacy HMAC-signed HTTP dispatcher.
 *
 * Resolves a registered ToolHandler via ToolRegistry and invokes it directly
 * inside the same PHP process — no network hop, no signature verification.
 */
final class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{ok:bool, tool:string, output?:mixed, error?:string, messages?:mixed}
     */
    public function call(Tool $tool, ToolContext $ctx, array $params): array
    {
        $handler = $this->registry->resolve($tool->name);
        if ($handler === null) {
            return [
                'ok' => false,
                'tool' => $tool->name,
                'error' => 'handler_not_registered',
                'messages' => "Tool handler for '{$tool->name}' is not registered in ToolRegistry.",
            ];
        }

        try {
            $output = $handler->handle($params, $ctx);

            return [
                'ok' => true,
                'tool' => $tool->name,
                'output' => $output,
            ];
        } catch (ValidationException $e) {
            return [
                'ok' => false,
                'tool' => $tool->name,
                'error' => 'validation_failed',
                'messages' => $e->errors(),
            ];
        } catch (Throwable $e) {
            report($e);

            return [
                'ok' => false,
                'tool' => $tool->name,
                'error' => 'tool_execution_failed',
                'messages' => $e->getMessage(),
            ];
        }
    }

    /**
     * Resolve a tool model by name from the registry's catalog.
     * Used by AgentLoop to look up description/json_schema/confirmation flag
     * without needing DB registration for built-in or runtime tools.
     */
    public function describe(ToolHandler $handler): Tool
    {
        return new Tool([
            'name' => $handler->name(),
            'description' => $handler->description(),
            'json_schema' => $handler->jsonSchema(),
            'requires_confirmation' => $handler->requiresConfirmation(),
            'is_active' => true,
        ]);
    }
}

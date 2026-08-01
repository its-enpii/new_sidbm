<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Contracts;

/**
 * Atomic, JSON-shaped tool handler.
 *
 * Implementations live in the host app (domain layer). They must never
 * return HTML, run DB migrations, or start long-running processes without
 * honouring the orchestrator's confirmation contract.
 */
interface AssistantToolHandler
{
    /**
     * The tool name used by the LLM (snake_case).
     */
    public function toolName(): string;

    /**
     * Human-facing description published to the orchestrator tool registry.
     * Keep one short paragraph; the LLM uses it for tool selection.
     */
    public function description(): string;

    /**
     * Whether the orchestrator must collect user confirmation before calling
     * the live execution.
     */
    public function requiresConfirmation(): bool;

    /**
     * JSON Schema (draft-07 subset accepted by the orchestrator).
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * Permission key from the host's catalog (mapped 1:1 by the host's
     * config('permissions.tool_map')). Empty = no permission check.
     */
    public function requiredPermission(): string;

    /**
     * Run (or preview) the tool.
     *
     * Return shape guidance:
     *  - Read tools:        ['items' => [...], 'match_count' => int, 'needs_clarification' => bool, ...]
     *  - Write preview:     ['preview' => true, 'needs_confirmation' => true, 'plan' => [...], 'summary' => '...', 'proposed_params' => [...]]
     *  - Write executed:    ['ok' => true, 'id' => ..., 'summary' => '...']
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function execute(array $params, AssistantActor $actor, ToolContext $context): array;
}
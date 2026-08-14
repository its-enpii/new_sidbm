<?php

declare(strict_types=1);

namespace Enpii\Assistant\Contracts;

/**
 * A single tool the assistant may invoke.
 *
 * Host app registers concrete handlers via ToolRegistry. Handlers are
 * resolved by name and invoked in-process — no HTTP hop, no HMAC.
 */
interface ToolHandler
{
    /**
     * Stable tool name used in LLM function calls and stored in
     * ai_tools.name. Must be unique across the registry.
     */
    public function name(): string;

    /**
     * Human-readable description shown to the LLM during tool selection.
     */
    public function description(): string;

    /**
     * JSON schema for tool arguments (subset of JSON Schema Draft 7).
     *
     * Light validation against this schema happens in AgentLoop::run()
     * (top-level type: object, required list, and per-property type).
     */
    public function jsonSchema(): array;

    /**
     * When true, the agent loop previews the tool first and asks for
     * user confirmation before invoking with confirm=true.
     */
    public function requiresConfirmation(): bool;

    /**
     * Execute the tool. Throw ValidationException for invalid args;
     * any other Throwable is caught by ToolExecutor and returned as
     * a generic failure to the LLM.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> structured output for the LLM
     */
    public function handle(array $params, ToolContext $ctx): array;
}

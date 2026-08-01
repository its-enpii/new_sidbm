<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Contracts;

/**
 * Marker contract for whatever user identity the host application resolves
 * a tool call against.
 *
 * The host binds an implementation of this contract (typically a thin proxy
 * around its own User model) so the package never hard-depends on the host's
 * User class.
 */
interface AssistantActor
{
    public function id(): string|int;

    public function externalId(): string|int;

    public function isActive(): bool;

    public function tenantId(): int|string|null;

    public function hasPermission(string $key): bool;

    /**
     * Human-readable name used in orchestrator audit logs.
     */
    public function displayName(): ?string;
}
<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Support;

use Illuminate\Http\Request;

/**
 * Host-provided hooks for the session-token endpoint.
 *
 * Three responsibilities:
 *  1) Decide whether the authenticated user may use the assistant widget.
 *  2) Map the host user to its external_user_id (e.g. row_id).
 *  3) Provide the tenant code the orchestrator should scope to.
 *
 * Bind via the application service provider:
 *
 *   $this->app->singleton(SessionTokenAuthorizer::class, function ($app) {
 *       return new class extends SessionTokenAuthorizer {
 *           public function allows(mixed $user): bool
 *           {
 *               return $user instanceof \App\Models\User
 *                   && $user->isActive()
 *                   && $user->can('assistant.use');
 *           }
 *           public function externalId(mixed $user): string { return (string) $user->row_id; }
 *           public function displayName(mixed $user): ?string { return $user->name; }
 *           public function resolveTenantCode(Request $r): ?string { return optional($r->tenant)->code; }
 *       };
 *   });
 */
abstract class SessionTokenAuthorizer
{
    /** @return bool */
    abstract public function allows(mixed $user): bool;

    abstract public function externalId(mixed $user): string;

    abstract public function displayName(mixed $user): ?string;

    abstract public function resolveTenantCode(Request $request): ?string;

    public function denialReason(mixed $user): string
    {
        return 'missing permission: assistant.use';
    }
}
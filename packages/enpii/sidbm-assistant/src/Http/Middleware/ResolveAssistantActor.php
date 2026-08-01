<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Http\Middleware;

use Closure;
use Enpii\SidbmAssistant\Contracts\AssistantActor;
use Enpii\SidbmAssistant\Support\ActorResolver;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Binds the orchestrator tool call to a real tenant user.
 *
 * Body shape:
 *   { tool, external_user_id, params, ts }
 *
 * external_user_id is whatever string/numeric id the host exposed to the
 * orchestrator when minting the session. The actual lookup is delegated to
 * ActorResolver (host-bound).
 */
final class ResolveAssistantActor
{
    public function __construct(
        private readonly ActorResolver $resolver,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $externalId = $request->input('external_user_id');

        // Fallback: some clients only put fields in raw JSON body.
        if (($externalId === null || $externalId === '') && str_contains((string) $request->header('Content-Type'), 'json')) {
            $decoded = json_decode($request->getContent(), true);
            if (is_array($decoded) && array_key_exists('external_user_id', $decoded)) {
                $externalId = $decoded['external_user_id'];
                $request->merge([
                    'external_user_id' => $decoded['external_user_id'] ?? null,
                    'tool' => $decoded['tool'] ?? $request->input('tool'),
                    'params' => $decoded['params'] ?? $request->input('params', []),
                ]);
            }
        }

        if ($externalId === null || $externalId === '' || is_array($externalId)) {
            return response()->json(['error' => 'external_user_id required'], 400);
        }

        try {
            $actor = $this->resolver->resolve((string) $externalId);
        } catch (Throwable) {
            return response()->json(['error' => 'unknown external_user_id'], 403);
        }

        if (! $actor instanceof AssistantActor) {
            return response()->json(['error' => 'unknown external_user_id'], 403);
        }

        if (! $actor->isActive()) {
            return response()->json(['error' => 'user inactive'], 403);
        }

        // Tenant guard: tool callbacks must stay inside the tenant context
        // the host middleware already resolved from the request host.
        $ctxTenant = (int) ($request->attributes->get('assistant_tenant_id') ?? 0);
        if ($ctxTenant > 0 && (int) $actor->tenantId() !== $ctxTenant) {
            return response()->json(['error' => 'user not in this tenant'], 403);
        }

        $request->attributes->set('assistant_actor', $actor);

        return $next($request);
    }
}
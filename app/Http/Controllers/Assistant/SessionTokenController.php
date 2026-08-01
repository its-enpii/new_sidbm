<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Domain\Access\Services\PermissionChecker;
use App\Services\Assistant\OrchestratorClient;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SessionTokenController
{
    public function __construct(
        private readonly OrchestratorClient $client,
        private readonly PermissionChecker $permissions,
        private readonly TenantContext $tenant,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (! $this->permissions->allows($user, 'assistant.use')) {
            return response()->json(['error' => 'missing permission: assistant.use'], 403);
        }

        if (! $this->client->isConfigured() || ! config('assistant.widget_enabled')) {
            return response()->json(['error' => 'assistant widget disabled'], 503);
        }

        try {
            $tenantCode = null;
            try {
                if ($this->tenant->isInitialized()) {
                    $tenantCode = (string) $this->tenant->tenant()->code;
                }
            } catch (Throwable) {
                $tenantCode = null;
            }

            $personaSlug = $request->query('persona');
            $personaSlug = is_string($personaSlug) && $personaSlug !== ''
                ? $personaSlug
                : (string) config('assistant.default_persona_slug', '');
            $personaSlug = $personaSlug !== '' ? $personaSlug : null;

            // URL that the orchestrator process uses to call back SIDBM tools.
            // Prefer ASSISTANT_ADAPTER_BASE_URL when APP_URL is only browser-facing
            // (e.g. orchestrator on host → http://127.0.0.1:8080).
            $adapterBase = (string) (config('assistant.adapter_base_url') ?: config('app.url'));

            $issued = $this->client->issueSessionToken(
                externalUserId: (string) $user->row_id,
                displayName: is_string($user->name) ? $user->name : null,
                tenantCode: $tenantCode,
                personaSlug: $personaSlug,
                adapterBaseUrl: $adapterBase,
            );

            return response()->json([
                'session_token' => $issued['session_token'],
                'expires_at' => $issued['expires_at'],
                'endpoint' => $issued['public_base_url'],
                'conversation_id' => $issued['conversation_id'],
                'persona' => $issued['persona'],
                'adapter' => $issued['adapter'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'failed to issue session token',
                'detail' => config('app.debug') ? $e->getMessage() : null,
            ], 502);
        }
    }
}

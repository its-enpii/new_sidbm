<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Http\Controllers;

use Enpii\SidbmAssistant\Services\OrchestratorClient;
use Enpii\SidbmAssistant\Support\SessionTokenAuthorizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SessionTokenController
{
    public function __construct(
        private readonly OrchestratorClient $client,
        private readonly SessionTokenAuthorizer $authorizer,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        if (! $this->authorizer->allows($user)) {
            return response()->json(['error' => $this->authorizer->denialReason($user)], 403);
        }

        if (! $this->client->isConfigured() || ! (bool) config('assistant.widget_enabled')) {
            return response()->json(['error' => 'assistant widget disabled'], 503);
        }

        try {
            $tenantCode = $this->authorizer->resolveTenantCode($request);
            $personaSlug = $this->resolvePersonaSlug($request);

            $adapterBase = (string) (config('assistant.adapter_base_url') ?: config('app.url'));

            $issued = $this->client->issueSessionToken(
                externalUserId: (string) $this->authorizer->externalId($user),
                displayName: $this->authorizer->displayName($user),
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

    private function resolvePersonaSlug(Request $request): ?string
    {
        $persona = $request->query('persona');
        if (is_string($persona) && $persona !== '') {
            return $persona;
        }

        $fallback = (string) config('assistant.default_persona_slug', '');

        return $fallback !== '' ? $fallback : null;
    }
}
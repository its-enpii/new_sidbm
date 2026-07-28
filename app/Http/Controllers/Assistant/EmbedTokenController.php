<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assistant;

use App\Domain\Access\Services\PermissionChecker;
use App\Services\EncompletionClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class EmbedTokenController
{
    public function __construct(
        private readonly EncompletionClient $client,
        private readonly PermissionChecker $permissions,
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

        if (! $this->client->isConfigured() || ! config('encompletion.widget_enabled')) {
            return response()->json(['error' => 'assistant widget disabled'], 503);
        }

        try {
            $issued = $this->client->issueEmbedToken((string) $user->row_id);

            return response()->json([
                'embed_token' => $issued['embed_token'],
                'expires_at' => $issued['expires_at'],
                'endpoint' => $this->client->publicBaseUrl(),
                'widget_script' => $this->client->widgetScriptUrl(),
                'persona_config' => $issued['persona_config'],
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'failed to issue embed token'], 502);
        }
    }
}

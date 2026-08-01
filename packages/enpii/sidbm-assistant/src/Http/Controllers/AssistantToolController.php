<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Http\Controllers;

use Enpii\SidbmAssistant\Contracts\AssistantActor;
use Enpii\SidbmAssistant\Contracts\ToolContext;
use Enpii\SidbmAssistant\Services\ToolDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class AssistantToolController
{
    public function __construct(
        private readonly ToolDispatcher $dispatcher,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $tool = (string) $request->input('tool', '');
        if ($tool === '') {
            $tool = (string) $request->route('tool', '');
        }
        if ($tool === '') {
            return response()->json(['error' => 'tool is required'], 400);
        }

        $actor = $request->attributes->get('assistant_actor');
        if (! $actor instanceof AssistantActor) {
            return response()->json(['error' => 'actor unresolved'], 401);
        }

        $params = $request->input('params');
        if (! is_array($params)) {
            $params = [];
        }

        $tenantId = (int) ($request->attributes->get('assistant_tenant_id') ?? 0);
        $tenantCode = $request->attributes->get('assistant_tenant_code');
        $context = new ToolContext(
            tenantId: $tenantId,
            tenantCode: is_string($tenantCode) ? $tenantCode : null,
        );

        try {
            $output = $this->dispatcher->dispatch($tool, $params, $actor, $context);

            return response()->json([
                'ok' => true,
                'tool' => $tool,
                'output' => $output,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'messages' => $e->errors(),
            ], 422);
        } catch (RuntimeException $e) {
            $status = str_starts_with($e->getMessage(), 'Unknown tool') ? 404 : 400;

            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'ok' => false,
                'error' => 'tool execution failed',
            ], 500);
        }
    }
}
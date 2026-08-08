<?php

declare(strict_types=1);

namespace Enpii\Assistant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Enpii\Assistant\Contracts\SessionResolver;
use Enpii\Assistant\Models\Persona;

final class PersonaInfoController
{
    public function __construct(
        private readonly SessionResolver $sessions,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $ctx = $this->sessions->resolve();
        $persona = null;

        if (! empty($ctx['persona_slug'])) {
            $persona = Persona::findBySlug((string) $ctx['persona_slug']);
        }

        if ($persona === null) {
            $persona = Persona::findDefault();
        }

        if ($persona === null) {
            return response()->json([
                'configured' => false,
                'persona' => null,
            ]);
        }

        return response()->json([
            'configured' => true,
            'persona' => [
                'id' => $persona->id,
                'slug' => $persona->slug,
                'name' => $persona->name,
            ],
        ]);
    }
}
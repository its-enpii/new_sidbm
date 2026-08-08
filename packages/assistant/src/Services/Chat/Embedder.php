<?php

declare(strict_types=1);

namespace Enpii\Assistant\Services\Chat;

/**
 * Embedding provider. Concrete = ModelGateway; tests can substitute.
 */
interface Embedder
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}

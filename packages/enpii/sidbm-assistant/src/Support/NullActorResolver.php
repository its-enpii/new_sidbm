<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Support;

use RuntimeException;

final class NullActorResolver extends ActorResolver
{
    public function resolve(string $externalId): ?object
    {
        throw new RuntimeException(
            'No ActorResolver is bound. Bind one in your application service provider.'
        );
    }
}
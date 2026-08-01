<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant\Support;

/**
 * Host-provided strategy to translate `external_user_id` to an AssistantActor.
 *
 * Bind a concrete implementation in your application service provider, e.g.:
 *
 *   $this->app->singleton(ActorResolver::class, function () {
 *       return new EloquentActorResolver(User::class, 'row_id');
 *   });
 *
 * The default binding raises — packages never resolve identity on their own.
 */
abstract class ActorResolver
{
    /**
     * @return \Enpii\SidbmAssistant\Contracts\AssistantActor|null
     *  Return null when no user matches, throw to mark a hard auth failure.
     */
    abstract public function resolve(string $externalId): ?object;
}
<?php

declare(strict_types=1);

namespace Enpii\Assistant\Facades;

use Enpii\Assistant\AssistantServiceProvider;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Enpii\Assistant\Services\Tools\ToolRegistry registry()
 * @method static \Enpii\Assistant\Services\Chat\AgentLoop agent()
 *
 * @see AssistantServiceProvider
 */
class Assistant extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'assistant';
    }
}

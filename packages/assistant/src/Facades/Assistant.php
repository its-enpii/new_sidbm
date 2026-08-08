<?php

declare(strict_types=1);

namespace Enpii\Assistant\Facades;

use Illuminate\Support\Facades\Facade;
use Enpii\Assistant\Services\Tools\ToolRegistry;

/**
 * @method static \Enpii\Assistant\Services\Tools\ToolRegistry registry()
 * @method static \Enpii\Assistant\Services\Chat\AgentLoop agent()
 *
 * @see \Enpii\Assistant\AssistantServiceProvider
 */
class Assistant extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'assistant';
    }
}
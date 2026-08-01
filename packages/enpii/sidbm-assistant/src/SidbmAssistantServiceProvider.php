<?php

declare(strict_types=1);

namespace Enpii\SidbmAssistant;

use Enpii\SidbmAssistant\Contracts\ToolRegistry;
use Enpii\SidbmAssistant\Http\Middleware\ResolveAssistantActor;
use Enpii\SidbmAssistant\Http\Middleware\VerifyOrchestratorSignature;
use Enpii\SidbmAssistant\Services\OrchestratorClient;
use Enpii\SidbmAssistant\Services\ToolDispatcher;
use Enpii\SidbmAssistant\Support\ActorResolver;
use Enpii\SidbmAssistant\Support\InMemoryToolRegistry;
use Enpii\SidbmAssistant\Support\NullActorResolver;
use Enpii\SidbmAssistant\Support\NullSessionTokenAuthorizer;
use Enpii\SidbmAssistant\Support\SessionTokenAuthorizer;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

final class SidbmAssistantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/assistant.php', 'assistant');

        $this->app->singleton(OrchestratorClient::class, static fn (): OrchestratorClient => new OrchestratorClient());

        $this->app->singleton(ToolRegistry::class, function (Container $app): ToolRegistry {
            $registry = new InMemoryToolRegistry();
            foreach ((array) config('assistant.tools', []) as $abstract) {
                if (is_string($abstract) && class_exists($abstract)) {
                    $registry->register($app->make($abstract));
                }
            }

            return $registry;
        });

        $this->app->singleton(ToolDispatcher::class, function (Container $app): ToolDispatcher {
            return new ToolDispatcher($app->make(ToolRegistry::class));
        });

        $this->app->singleton(ActorResolver::class, static fn (): ActorResolver => new NullActorResolver());
        $this->app->singleton(SessionTokenAuthorizer::class, static fn (): SessionTokenAuthorizer => new NullSessionTokenAuthorizer());
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/assistant.php' => config_path('assistant.php'),
        ], 'sidbm-assistant-config');

        $this->loadRoutesFrom(__DIR__.'/../routes/assistant.php');

        if ((bool) config('assistant.widget_enabled', false)) {
            $this->loadViewsFrom(__DIR__.'/../resources/views', 'sidbm-assistant');
        }

        $this->registerMiddlewareAliases();
    }

    private function registerMiddlewareAliases(): void
    {
        /** @var Router $router */
        $router = $this->app['router'];
        $router->aliasMiddleware('assistant.signature', VerifyOrchestratorSignature::class);
        $router->aliasMiddleware('assistant.actor', ResolveAssistantActor::class);
    }
}
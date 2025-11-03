<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Provider;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Middleware\Contracts\Middleware\DispatcherInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Middleware\MiddlewarePipeline;
use Maduser\Argon\Prophecy\Support\Tag;
use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Factory\RoutingRequestHandlerFactory;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Middleware\RouteMatcherMiddleware;
use Maduser\Argon\Routing\RequestHandlerResolver;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\Store\ContainerStore;
use Override;
use Psr\Http\Server\RequestHandlerInterface;

class RouteServiceProvider extends AbstractServiceProvider
{
    /**
     * @throws ContainerException
     */
    #[Override]
    public function register(ArgonContainer $container): void
    {
        $parameters = $container->getParameters();
        $store = (string) $parameters->get('routing.store', ContainerStore::class);

        $container->set(RouteManager::class, args: ['store' => $store]);

        $container->set(RouterInterface::class, Router::class, [
            'pipelines' => PipelineManagerInterface::class,
        ]);

        $container->set(RouteMatcherInterface::class, RouteMatcher::class);

        /**
         * Override the default RequestHandlerInterface
         */
        $container->set(RequestHandlerResolverInterface::class, RequestHandlerResolver::class);

        $container->set(RoutingRequestHandlerFactory::class);

        $container->set(RequestHandlerInterface::class, MiddlewarePipeline::class)
            ->factory(RoutingRequestHandlerFactory::class, 'create');

        /**
         * Override the default dispatcher middleware
         */
        $container->set(DispatcherInterface::class, RouteDispatcherMiddleware::class)
            ->tag([Tag::MIDDLEWARE_HTTP => ['priority' => 6000, 'group' => ['api', 'web']]]);
    }
}

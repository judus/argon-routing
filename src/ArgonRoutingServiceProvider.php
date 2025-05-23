<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Container\AbstractServiceProvider;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Contracts\Http\Server\Middleware\DispatcherInterface;
use Maduser\Argon\Prophecy\Support\Tag;
use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Maduser\Argon\Routing\Contracts\RouteContextInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Store\ContainerStore;

class ArgonRoutingServiceProvider extends AbstractServiceProvider
{
    /**
     * @throws ContainerException
     */
    public function register(ArgonContainer $container): void
    {
        $parameters = $container->getParameters();
        $store = (string) $parameters->get('routing.store', ContainerStore::class);

        $container->set(RouteStoreInterface::class, ContainerStore::class)
            ->tag(['routing.store']);

        $container->set(RouteManager::class, args: ['store' => $store])
            ->tag(['routing.manager']);

        $container->set(RouterInterface::class, ArgonRouter::class)
            ->tag(['routing.router']);

        $container->set(RouteMatcherInterface::class, RouteMatcher::class)
            ->tag(['routing.matcher']);

        $container->set(RouteContextInterface::class, RouteContext::class)
            ->tag(['routing.context']);

        $container->set(RequestHandlerResolverInterface::class, RequestHandlerResolver::class)
            ->tag(['middleware.pipeline.resolver']);

        /**
         * Override the default dispatcher middleware
         */
        $container->set(DispatcherInterface::class, RouteDispatcherMiddleware::class)
            ->tag([Tag::MIDDLEWARE_HTTP => ['priority' => 6000, 'group' => ['api', 'web']]]);
    }
}

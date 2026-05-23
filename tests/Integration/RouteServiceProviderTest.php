<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Contracts\ServiceDescriptorInterface;
use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Factory\RoutingRequestHandlerFactory;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Provider\RouteServiceProvider;
use Maduser\Argon\Routing\RequestHandlerResolver;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\Store\ContainerStore;
use Maduser\Argon\Routing\Store\InMemoryStore;
use Maduser\Argon\Routing\Tests\Integration\Stubs\NullPipelineManager;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Middleware\MiddlewarePipeline;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class RouteServiceProviderTest extends TestCase
{
    public function testRegistersDefaultBindings(): void
    {
        $container = $this->bootContainer();

        $provider = new RouteServiceProvider();
        $provider->register($container);

        $routeManager = $this->getDescriptor($container, RouteManager::class);
        self::assertSame(RouteManager::class, $routeManager->getConcrete());
        self::assertTrue($routeManager->hasArgument('store'));
        self::assertSame(ContainerStore::class, $routeManager->getArgument('store'));

        $router = $this->getDescriptor($container, RouterInterface::class);
        self::assertSame(Router::class, $router->getConcrete());
        self::assertTrue($router->hasArgument('pipelines'));
        self::assertSame(PipelineManagerInterface::class, $router->getArgument('pipelines'));

        $resolverBinding = $this->getDescriptor($container, RequestHandlerResolverInterface::class);
        self::assertSame(RequestHandlerResolver::class, $resolverBinding->getConcrete());

        $matcherBinding = $this->getDescriptor($container, RouteMatcherInterface::class);
        self::assertSame(RouteMatcher::class, $matcherBinding->getConcrete());

        $factoryBinding = $this->getDescriptor($container, RoutingRequestHandlerFactory::class);
        self::assertSame(RoutingRequestHandlerFactory::class, $factoryBinding->getConcrete());

        $requestHandler = $this->getDescriptor($container, RequestHandlerInterface::class);
        self::assertSame(MiddlewarePipeline::class, $requestHandler->getConcrete());
        self::assertTrue($requestHandler->hasFactory());
        self::assertSame(RoutingRequestHandlerFactory::class, $requestHandler->getFactoryClass());
        self::assertSame('create', $requestHandler->getFactoryMethod());

        $dispatcher = $this->getDescriptor($container, RouteDispatcherMiddleware::class);
        self::assertSame(RouteDispatcherMiddleware::class, $dispatcher->getConcrete());

        $meta = $container->getTaggedMeta('middleware.http');
        self::assertArrayHasKey(RouteDispatcherMiddleware::class, $meta);
        self::assertSame(['api', 'web'], $meta[RouteDispatcherMiddleware::class]['group']);
        self::assertSame(6000, $meta[RouteDispatcherMiddleware::class]['priority']);
    }

    public function testRespectsConfiguredRouteStore(): void
    {
        $container = $this->bootContainer();
        $container->getParameters()->set('routing.store', InMemoryStore::class);

        $provider = new RouteServiceProvider();
        $provider->register($container);

        $routeManager = $this->getDescriptor($container, RouteManager::class);
        self::assertSame(InMemoryStore::class, $routeManager->getArgument('store'));
    }

    private function bootContainer(): ArgonContainer
    {
        $container = new ArgonContainer();
        $container->set(PipelineManagerInterface::class, NullPipelineManager::class);

        return $container;
    }

    private function getDescriptor(ArgonContainer $container, string $id): ServiceDescriptorInterface
    {
        $descriptor = $container->getDescriptor($id);

        self::assertNotNull($descriptor, sprintf('Expected service descriptor for [%s] to be registered.', $id));

        return $descriptor;
    }
}

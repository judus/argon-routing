<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use Maduser\Argon\Routing\RouteContext;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\FrozenRouteContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class RouteContextTest extends TestCase
{
    public function testGetRouteCachesMatchedRoute(): void
    {
        $container = new ArgonContainer();
        $routes = new RouteManager();
        $router = new Router($container, $routes);
        $router->get('/items/{id}', 'ItemController@show', middleware: ['auth'], name: 'items.show');

        $factory = new Psr17Factory();
        $matcher = new RouteMatcher($routes);
        $request = $factory->createServerRequest('GET', '/items/42');
        $context = new RouteContext($matcher, $request);

        $first = $context->getRoute();

        self::assertSame('items.show', $first->getName());
        self::assertSame(['auth'], $first->getMiddlewares());
        self::assertSame('42', $first->getArguments()['id'] ?? null);

        // Modify the request; cached route should stay the same.
        $second = $context->getRoute($factory->createServerRequest('GET', '/items/99'));
        self::assertSame($first, $second);

    }

    public function testFrozenRouteContextAlwaysReturnsProvidedRoute(): void
    {
        $container = new ArgonContainer();
        $routes = new RouteManager();
        $router = new Router($container, $routes);
        $router->get('/static', 'StaticController@show', name: 'static.show');

        $factory = new Psr17Factory();
        $context = new RouteContext(new RouteMatcher($routes), $factory->createServerRequest('GET', '/static'));
        $resolved = $context->getRoute();

        $frozen = new FrozenRouteContext($resolved);
        self::assertSame($resolved, $frozen->getRoute());
        self::assertSame($resolved, $frozen->getRoute($factory->createServerRequest('GET', '/other')));
    }
}

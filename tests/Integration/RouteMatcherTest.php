<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Routing\ArgonRouter;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\RouteMatcher;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

final class RouteMatcherTest extends TestCase
{
    public function testMatchReturnsRouteAndCapturedArguments(): void
    {
        $container = new ArgonContainer();
        $routes = new RouteManager();

        $router = new ArgonRouter($container, $routes);
        $router->get('/users/{id}', 'UserController@show');

        $matcher = new RouteMatcher($routes);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/users/42');

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $matched = $matcher->match($request);

        self::assertSame('GET', $matched->getMethod());
        self::assertSame('/users/{id}', $matched->getPattern());
        self::assertSame('UserController@show', $matched->getHandler());
        self::assertSame(['id' => '42'], $matched->getArguments());
    }
}

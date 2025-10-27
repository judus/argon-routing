<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Unit;

use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Route;
use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase
{
    public function testSettersUpdateRouteState(): void
    {
        $route = new Route(
            method: 'POST',
            name: 'users.store',
            pattern: '/users',
            handler: ['UserController', 'store'],
        );

        $route->setCompiled('#^/users$#');
        $route->setPipelineId('pipeline__123');
        $route->setMiddlewares(['auth', 'throttle']);
        $route->setArguments(['id' => '10']);

        self::assertSame('POST', $route->getMethod());
        self::assertSame('users.store', $route->getName());
        self::assertSame('#^/users$#', $route->getCompiled());
        self::assertSame('pipeline__123', $route->getPipelineId());
        self::assertSame(['auth', 'throttle'], $route->getMiddlewares());
        self::assertSame(['id' => '10'], $route->getArguments());

        $array = $route->toArray();
        self::assertSame('UserController@store', $array['handler']);
        self::assertSame('pipeline__123', $array['pipelineId']);
    }

    public function testGetCompiledThrowsWhenMissing(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'users.index',
            pattern: '/users',
            handler: 'UserController@index',
        );

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Route has no compiled pattern');

        $route->getCompiled();
    }

    public function testToArrayWithClosureHandler(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'closure',
            pattern: '/closure',
            handler: static fn() => null,
        );

        $result = $route->toArray();

        self::assertArrayHasKey('handler', $result);
        self::assertStringContainsString('Closure<', $result['handler']);
    }
}

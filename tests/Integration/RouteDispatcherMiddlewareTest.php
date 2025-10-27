<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\FrozenRouteContext;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RecordingContainer;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RecordingResultContext;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RouteDispatcherRecordingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class RouteDispatcherMiddlewareTest extends TestCase
{
    public function testInvokesResolvedServiceInvokerAndStoresResult(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'items.show',
            pattern: '/items/{id}',
            handler: 'ItemController@show',
            compiled: '#^/items/(?P<id>[^/]+)/?$#',
            arguments: ['id' => '42'],
        );

        $container = new RecordingContainer([
            '/items/{id}' => fn(array $args) => 'result:' . $args['id'],
        ]);

        $result = new RecordingResultContext();
        $context = new FrozenRouteContext($route);
        $psr17 = new Psr17Factory();

        $middleware = new RouteDispatcherMiddleware($container, $context, $result);
        $response = $middleware->process(
            $psr17->createServerRequest('GET', '/items/42'),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame(['result:42'], $result->values);
        self::assertSame(['/items/{id}'], $container->requestedIds);
    }

    public function testClosureHandlersInvokeDirectly(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'closure',
            pattern: '/closure',
            handler: fn(array $args): string => 'closure:' . implode(',', $args),
            arguments: ['foo' => 'bar'],
        );

        $container = new RecordingContainer([]);
        $result = new RecordingResultContext();
        $context = new FrozenRouteContext($route);
        $psr17 = new Psr17Factory();

        $middleware = new RouteDispatcherMiddleware($container, $context, $result);
        $middleware->process(
            $psr17->createServerRequest('GET', '/closure'),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );

        self::assertSame(['closure:bar'], $result->values);
        self::assertSame([], $container->requestedIds);
    }

    public function testThrowsWhenHandlerCallsMiddlewareItself(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'loop',
            pattern: '/loop',
            handler: RouteDispatcherMiddleware::class,
        );

        $container = new RecordingContainer([]);
        $result = new RecordingResultContext();
        $context = new FrozenRouteContext($route);
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $context, $result);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Infinite RouteDispatcherMiddleware loop detected.');

        $middleware->process(
            $psr17->createServerRequest('GET', '/loop'),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }

    public function testThrowsWhenResolvedInvokerIsNotCallable(): void
    {
        // Simulate a misconfigured container entry that returns a scalar instead of a callable.
        $route = new Route(
            method: 'GET',
            name: 'broken',
            pattern: '/broken',
            handler: 'BrokenController@show',
        );

        $container = new class('/broken') implements ContainerInterface {
            public function __construct(private readonly string $routeId)
            {
            }

            public function get(string $id): mixed
            {
                if ($id === $this->routeId) {
                    return 'not-callable';
                }

                throw new RuntimeException("Service [$id] not mocked.");
            }

            public function has(string $id): bool
            {
                return $id === $this->routeId;
            }
        };
        $result = new RecordingResultContext();
        $context = new FrozenRouteContext($route);
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $context, $result);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Handler [BrokenController@show] is not callable (got: string).');

        $middleware->process(
            $psr17->createServerRequest('GET', '/broken'),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }
}

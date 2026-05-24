<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RecordingContainer;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RecordingResultResponder;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\RouteDispatcherRecordingHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class RouteDispatcherMiddlewareTest extends TestCase
{
    public function testInvokesResolvedServiceInvokerAndRespondsWithResult(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'items.show',
            pattern: '/items/{id}',
            handler: 'ItemController@show',
            compiled: '#^/items/(?P<id>[^/]+)/?$#',
            arguments: ['id' => '42'],
        );

        /** @param array{id: string} $args */
        $invoker = static function (array $args): string {
            $id = $args['id'] ?? '';
            return 'result:' . (is_scalar($id) ? (string) $id : '');
        };
        $container = new RecordingContainer(['/items/{id}' => $invoker]);

        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();

        $middleware = new RouteDispatcherMiddleware($container, $responder);
        $response = $middleware->process(
            $psr17->createServerRequest('GET', '/items/42')
                ->withAttribute(RouteInterface::class, $route),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );

        self::assertSame('recording', $response->getHeaderLine('X-Argon-Responder'));
        self::assertSame('result:42', (string) $response->getBody());
        self::assertSame(['result:42'], $responder->results);
        self::assertSame(['/items/{id}'], $container->requestedIds);
    }

    public function testClosureHandlersInvokeDirectly(): void
    {
        /** @param array<string, string> $args */
        $closureHandler = static function (array $args): string {
            $values = array_map(
                static fn(mixed $value): string => is_scalar($value) ? (string) $value : get_debug_type($value),
                array_values($args)
            );

            return 'closure:' . implode(',', $values);
        };

        $route = new Route(
            method: 'GET',
            name: 'closure',
            pattern: '/closure',
            handler: $closureHandler,
            arguments: ['foo' => 'bar'],
        );

        $container = new RecordingContainer([]);
        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();

        $middleware = new RouteDispatcherMiddleware($container, $responder);
        $middleware->process(
            $psr17->createServerRequest('GET', '/closure')
                ->withAttribute(RouteInterface::class, $route),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );

        self::assertSame(['closure:bar'], $responder->results);
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
        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $responder);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Infinite RouteDispatcherMiddleware loop detected.');

        $middleware->process(
            $psr17->createServerRequest('GET', '/loop')
                ->withAttribute(RouteInterface::class, $route),
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
            handler: [BrokenController::class, 'show'],
        );

        $container = new NonCallableRouteContainer('/broken');
        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $responder);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Handler [' . BrokenController::class . '::show] is not callable (got: string).');

        $middleware->process(
            $psr17->createServerRequest('GET', '/broken')
                ->withAttribute(RouteInterface::class, $route),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }

    public function testThrowsOnMalformedHandlerDefinition(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'malformed',
            pattern: '/malformed',
            handler: [null],
        );

        $container = new RecordingContainer([]);
        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $responder);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Malformed handler definition');

        $middleware->process(
            $psr17->createServerRequest('GET', '/malformed')
                ->withAttribute(RouteInterface::class, $route),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }

    public function testArrayHandlerRecursionDetection(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'loop',
            pattern: '/loop',
            handler: [RouteDispatcherMiddleware::class, 'process'],
        );

        $container = new RecordingContainer([]);
        $responder = new RecordingResultResponder();
        $psr17 = new Psr17Factory();
        $middleware = new RouteDispatcherMiddleware($container, $responder);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Infinite RouteDispatcherMiddleware loop detected.');

        $middleware->process(
            $psr17->createServerRequest('GET', '/loop')
                ->withAttribute(RouteInterface::class, $route),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }
}

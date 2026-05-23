<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Middleware\DispatchMiddleware;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\RoutePipeline;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class RouteDispatchTest extends TestCase
{
    public function testDispatchesRouteThroughConfiguredPipeline(): void
    {
        $trace = new CallTrace();

        $container = new ArgonContainer();
        $container->set(RecordingMiddlewareA::class, fn() => new RecordingMiddlewareA($trace))->shared();
        $container->set(RecordingMiddlewareB::class, fn() => new RecordingMiddlewareB($trace))->shared();

        $controller = new RecordingController($trace);
        $container->set(RecordingController::class, fn() => $controller)->shared();

        $pipeline = new RoutePipeline($container);
        $route = new Route(
            method: 'GET',
            name: 'items.show',
            pattern: '/items/{id}',
            handler: RecordingController::class,
            compiled: '#^/items/(?P<id>\d+)/?$#',
            middlewares: [RecordingMiddlewareA::class, RecordingMiddlewareB::class],
            arguments: ['id' => '123']
        );

        $psr17 = new Psr17Factory();

        $request = $psr17->createServerRequest('GET', '/items/123')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $finalHandler = new RecordingFinalHandler($psr17->createResponse());
        $middleware = new DispatchMiddleware($container, $pipeline);

        $response = $middleware->process($request, $finalHandler);

        self::assertSame($finalHandler->getResponse(), $response);
        self::assertSame(
            [
                'A:before',
                'B:before',
                'controller',
                'B:after',
                'A:after',
            ],
            $trace->events
        );

        $processedRequest = $finalHandler->handledRequest;
        self::assertInstanceOf(ServerRequestInterface::class, $processedRequest);
        self::assertSame('result:123', $processedRequest->getAttribute('rawResult'));
        self::assertSame(['id' => '123'], $controller->calls[0]['args'] ?? []);
    }

    public function testThrowsWhenRouteAttributeMissing(): void
    {
        $container = new ArgonContainer();
        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/missing');

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('No resolved route found in request.');

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }

    public function testThrowsWhenRouteHandlerIsClosure(): void
    {
        $container = new ArgonContainer();
        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'GET',
            name: 'closure',
            pattern: '/closure',
            handler: static fn(): string => 'nope'
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/closure')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Closure route handlers are not yet supported.');

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }

    public function testThrowsWhenHandlerIsNotCallable(): void
    {
        $container = new ArgonContainer();
        $container->set(NonCallableHandler::class)->shared();

        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'GET',
            name: 'non-callable',
            pattern: '/non-callable',
            handler: NonCallableHandler::class,
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/non-callable')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage(sprintf(
            'Handler [%1$s] is not callable (got: %1$s).',
            NonCallableHandler::class
        ));

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }

    public function testThrowsWhenRouteHandlerReferencesMiddlewareItself(): void
    {
        // Guard against accidentally wiring the middleware as the controller, which would recurse forever.
        $container = new ArgonContainer();
        $container->set(DispatchMiddleware::class, static fn() => static fn(array $args): string => 'noop')->shared();

        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'GET',
            name: 'self-loop',
            pattern: '/loop',
            handler: DispatchMiddleware::class,
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/loop')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Infinite DispatchMiddleware loop detected.');

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }

    public function testDispatchesRouteWithArrayHandler(): void
    {
        $trace = new CallTrace();

        $container = new ArgonContainer();
        $container->set(RecordingMiddlewareA::class, fn() => new RecordingMiddlewareA($trace))->shared();
        $container->set(RecordingMiddlewareB::class, fn() => new RecordingMiddlewareB($trace))->shared();
        $container->set(MethodBasedController::class, fn() => new MethodBasedController($trace))->shared();

        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'PATCH',
            name: 'items.update',
            pattern: '/items/{id}',
            handler: [MethodBasedController::class, 'show'],
            compiled: '#^/items/(?P<id>[^/]+)/?$#',
            middlewares: [RecordingMiddlewareA::class, RecordingMiddlewareB::class],
            arguments: ['id' => '321']
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('PATCH', '/items/321')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $finalHandler = new RecordingFinalHandler($psr17->createResponse());

        $response = $middleware->process($request, $finalHandler);

        self::assertSame($finalHandler->getResponse(), $response);
        self::assertSame(
            [
                'A:before',
                'B:before',
                'method.show',
                'B:after',
                'A:after',
            ],
            $trace->events
        );

        $processedRequest = $finalHandler->handledRequest;
        self::assertSame('method:321', $processedRequest?->getAttribute('rawResult'));
    }

    public function testDispatchThrowsOnMalformedHandlerDefinition(): void
    {
        $container = new ArgonContainer();
        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'POST',
            name: 'broken.handler',
            pattern: '/broken',
            handler: [],
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('POST', '/broken')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Malformed handler definition');

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }

    public function testDispatchesInvokeHandlerDefinedAsArray(): void
    {
        $container = new ArgonContainer();
        $container->set(InvokeArrayController::class, fn() => new InvokeArrayController())->shared();

        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'GET',
            name: 'invoke.array',
            pattern: '/invoke/{slug}',
            handler: [InvokeArrayController::class, '__invoke'],
            compiled: '#^/invoke/(?P<slug>[^/]+)$#',
            arguments: ['slug' => 'sample']
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/invoke/sample')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $response = $middleware->process(
            $request,
            new RecordingFinalHandler($psr17->createResponse())
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testThrowsWhenArrayHandlerMethodIsMissing(): void
    {
        $container = new ArgonContainer();
        $container->set(MethodMissingController::class, fn() => new MethodMissingController())->shared();

        $pipeline = new RoutePipeline($container);
        $middleware = new DispatchMiddleware($container, $pipeline);

        $route = new Route(
            method: 'DELETE',
            name: 'missing.method',
            pattern: '/missing',
            handler: [MethodMissingController::class, 'missing'],
        );

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('DELETE', '/missing')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Handler [' . MethodMissingController::class . '::missing] is not callable');

        $middleware->process($request, new RecordingFinalHandler($psr17->createResponse()));
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use Maduser\Argon\Routing\RequestHandlerResolver;
use Maduser\Argon\Routing\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class RequestHandlerResolverTest extends TestCase
{
    public function testResolvesHandlerUsingPipelineId(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'items.show',
            pattern: '/items/{id}',
            handler: 'ItemController@show',
            pipelineId: 'pipeline__abc',
            middlewares: [FirstMiddleware::class],
            arguments: ['id' => '1'],
        );

        $pipeline = new RecordingHandler();
        $store = new RecordingPipelineStore(['pipeline__abc' => $pipeline]);
        $testHandler = new TestHandler();
        $logger = new Logger('tests');
        $logger->pushHandler($testHandler);
        $resolver = new RequestHandlerResolver(new MatchingRouteMatcher($route), $store, $logger);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/items/1');
        $result = $resolver->resolve($request);

        self::assertNotSame($pipeline, $result);
        self::assertSame(['pipeline__abc'], $store->requested);
        self::assertTrue($testHandler->hasInfoThatContains('Matched route'));
        self::assertTrue($testHandler->hasInfoThatContains('RequestHandler resolved'));

        $response = $result->handle($request);

        self::assertTrue($pipeline->handled);
        self::assertSame($route, $pipeline->request?->getAttribute(RouteInterface::class));
        self::assertSame(200, $response->getStatusCode());
    }

    public function testResolvesHandlerWithMiddlewareStackWhenNoPipelineId(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'items.index',
            pattern: '/items',
            handler: 'ItemController@index',
            middlewares: [FirstMiddleware::class, SecondMiddleware::class],
        );

        $pipeline = new RecordingHandler();
        $store = new RecordingPipelineStore([
            (new MiddlewareStack([FirstMiddleware::class, SecondMiddleware::class]))->getId() => $pipeline,
        ]);
        $testHandler = new TestHandler();
        $logger = new Logger('tests');
        $logger->pushHandler($testHandler);

        $resolver = new RequestHandlerResolver(new MatchingRouteMatcher($route), $store, $logger);
        $request = (new Psr17Factory())->createServerRequest('GET', '/items');
        $result = $resolver->resolve($request);

        self::assertNotSame($pipeline, $result);
        self::assertTrue($testHandler->hasInfoThatContains('Matched route'));
        self::assertTrue($testHandler->hasInfoThatContains('RequestHandler resolved'));
        self::assertInstanceOf(MiddlewareStack::class, $store->requested[0]);
        self::assertSame([FirstMiddleware::class, SecondMiddleware::class], $store->requested[0]->toArray());

        $response = $result->handle($request);

        self::assertTrue($pipeline->handled);
        self::assertSame($route, $pipeline->request?->getAttribute(RouteInterface::class));
        self::assertSame(200, $response->getStatusCode());
    }
}

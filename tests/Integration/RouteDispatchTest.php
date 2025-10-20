<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Middleware\DispatchMiddleware;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\RoutePipeline;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\FrozenRouteContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

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

        $context = new FrozenRouteContext($route);

        $psr17 = new Psr17Factory();

        $request = $psr17->createServerRequest('GET', '/items/123')
            ->withAttribute(MatchedRouteInterface::class, $route);

        $finalHandler = new RecordingFinalHandler($psr17->createResponse());
        $middleware = new DispatchMiddleware($container, $pipeline, $context);

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
}

final class CallTrace
{
    /** @var list<string> */
    public array $events = [];

    public function add(string $event): void
    {
        $this->events[] = $event;
    }
}

final class RecordingMiddlewareA implements MiddlewareInterface
{
    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->trace->add('A:before');
        $response = $handler->handle($request);
        $this->trace->add('A:after');
        return $response;
    }
}

final class RecordingMiddlewareB implements MiddlewareInterface
{
    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->trace->add('B:before');
        $response = $handler->handle($request);
        $this->trace->add('B:after');
        return $response;
    }
}

final class RecordingController
{
    /** @var list<array{args: array<string, string>}> */
    public array $calls = [];

    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    public function __invoke(array $args): string
    {
        $this->trace->add('controller');
        $this->calls[] = ['args' => $args];
        return 'result:' . ($args['id'] ?? 'missing');
    }
}

final class RecordingFinalHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $handledRequest = null;

    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handledRequest = $request;
        return $this->response;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}

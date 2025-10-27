<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Middleware\Contracts\PipelineStoreInterface;
use Maduser\Argon\Middleware\MiddlewareStack as MiddlewarePipelineStack;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\RequestHandlerResolver;
use Maduser\Argon\Routing\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
            middlewares: ['auth'],
            arguments: ['id' => '1'],
        );

        $pipeline = new RecordingHandler();
        $store = new RecordingPipelineStore(['pipeline__abc' => $pipeline]);
        $testHandler = new TestHandler();
        $logger = new Logger('tests');
        $logger->pushHandler($testHandler);
        $resolver = new RequestHandlerResolver(new MatchingRouteMatcher($route), $store, $logger);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/items/1')
            ->withAttribute(RouteInterface::class, $route);
        $result = $resolver->resolve($request);

        self::assertSame($pipeline, $result);
        self::assertSame(['pipeline__abc'], $store->requested);
        self::assertTrue($testHandler->hasInfoThatContains('Matched route'));
        self::assertTrue($testHandler->hasInfoThatContains('RequestHandler resolved'));
    }

    public function testResolvesHandlerWithMiddlewareStackWhenNoPipelineId(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'items.index',
            pattern: '/items',
            handler: 'ItemController@index',
            middlewares: ['auth', 'throttle'],
        );

        $pipeline = new RecordingHandler();
        $store = new RecordingPipelineStore([
            (new MiddlewarePipelineStack(['auth', 'throttle']))->getId() => $pipeline,
        ]);
        $testHandler = new TestHandler();
        $logger = new Logger('tests');
        $logger->pushHandler($testHandler);

        $resolver = new RequestHandlerResolver(new MatchingRouteMatcher($route), $store, $logger);
        $request = (new Psr17Factory())->createServerRequest('GET', '/items')
            ->withAttribute(RouteInterface::class, $route);
        $result = $resolver->resolve($request);

        self::assertSame($pipeline, $result);
        self::assertTrue($testHandler->hasInfoThatContains('Matched route'));
        self::assertTrue($testHandler->hasInfoThatContains('RequestHandler resolved'));
        self::assertInstanceOf(MiddlewarePipelineStack::class, $store->requested[0]);
        self::assertSame(['auth', 'throttle'], $store->requested[0]->toArray());
    }
}

final class RecordingPipelineStore implements PipelineStoreInterface
{
    /** @var array<string, RequestHandlerInterface> */
    private array $pipelines;

    /** @var list<string|MiddlewarePipelineStack> */
    public array $requested = [];

    /** @param array<string, RequestHandlerInterface> $pipelines */
    public function __construct(array $pipelines)
    {
        $this->pipelines = $pipelines;
    }

    public function get($keyOrStack): RequestHandlerInterface
    {
        $this->requested[] = $keyOrStack;

        if ($keyOrStack instanceof MiddlewarePipelineStack) {
            $key = $keyOrStack->getId();
        } else {
            $key = (string) $keyOrStack;
        }

        if (!isset($this->pipelines[$key])) {
            throw new \RuntimeException("Pipeline [$key] not found.");
        }

        return $this->pipelines[$key];
    }

    public function register(\Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface $stack): self
    {
        $this->pipelines[$stack->getId()] = new RecordingHandler();
        return $this;
    }
}

final class RecordingHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        $psr17 = new Psr17Factory();
        return $psr17->createResponse();
    }
}

final class MatchingRouteMatcher implements \Maduser\Argon\Routing\Contracts\RouteMatcherInterface
{
    public function __construct(private Route $route)
    {
    }

    public function match(ServerRequestInterface $request): Route
    {
        return $this->route;
    }
}

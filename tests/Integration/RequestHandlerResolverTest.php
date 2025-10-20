<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Middleware\Contracts\PipelineStoreInterface;
use Maduser\Argon\Middleware\MiddlewareStack as MiddlewarePipelineStack;
use Maduser\Argon\Routing\RequestHandlerResolver;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\FrozenRouteContext;
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
        $resolver = new RequestHandlerResolver(new FrozenRouteContext($route), $store, $logger);

        $psr17 = new Psr17Factory();
        $result = $resolver->resolve($psr17->createServerRequest('GET', '/items/1'));

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

        $resolver = new RequestHandlerResolver(new FrozenRouteContext($route), $store, $logger);
        $result = $resolver->resolve();

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

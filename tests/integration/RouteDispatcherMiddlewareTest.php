<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Contracts\Http\Server\ResultContextInterface;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\FrozenRouteContext;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Infinite RouteDispatcherMiddleware loop detected.');

        $middleware->process(
            $psr17->createServerRequest('GET', '/loop'),
            new RouteDispatcherRecordingHandler($psr17->createResponse())
        );
    }
}

final class RecordingContainer implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $services;

    /** @var list<string> */
    public array $requestedIds = [];

    /** @param array<string, callable> $services */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    public function get(string $id): callable
    {
        $this->requestedIds[] = $id;

        if (!isset($this->services[$id])) {
            throw new RuntimeException("Service [$id] not found.");
        }

        return $this->services[$id];
    }

    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

final class RecordingResultContext implements ResultContextInterface
{
    /** @var list<mixed> */
    public array $values = [];

    public function set(mixed $result): ResultContextInterface
    {
        $this->values[] = is_array($result)
            ? implode(',', array_map(fn($key, $value) => $key . '=' . $value, array_keys($result), $result))
            : $result;

        return $this;
    }

    public function get(): mixed
    {
        return $this->values[array_key_last($this->values)] ?? null;
    }

    public function has(): bool
    {
        return $this->values !== [];
    }

    public function is(string $type): bool
    {
        $last = $this->get();
        return $last !== null && $last instanceof $type;
    }

    public function isString(): bool
    {
        return is_string($this->get());
    }

    public function isScalar(): bool
    {
        return is_scalar($this->get());
    }

    public function isClosure(): bool
    {
        return $this->get() instanceof \Closure;
    }

    public function isResponse(): bool
    {
        return $this->get() instanceof ResponseInterface;
    }

    public function isArray(): bool
    {
        return is_array($this->get());
    }

    public function isObject(): bool
    {
        $last = $this->get();
        return is_object($last) && !$this->isResponse();
    }

    public function isCallable(): bool
    {
        return is_callable($this->get());
    }
}

final class RouteDispatcherRecordingHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

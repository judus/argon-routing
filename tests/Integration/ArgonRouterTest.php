<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\RouteManager;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ArgonRouterTest extends TestCase
{
    public function testAddRegistersRouteAndPipeline(): void
    {
        $container = new ArgonContainer();
        $routes = new RouteManager();
        $pipelines = new RecordingPipelineManager();

        $router = new Router($container, $routes, $pipelines);
        $router->add('get', '/users/{id}', 'UserController@show');

        $registered = $routes->getRoutesFor('GET');
        self::assertArrayHasKey('/users/{id}', $registered);

        $route = $registered['/users/{id}'];
        self::assertSame('#^/users/(?P<id>[^/]+)/?$#', $route['compiled']);
        self::assertSame('UserController@show', $route['handler']);
        self::assertSame('pipeline__' . md5(json_encode([])), $route['pipelineId']);
        self::assertSame([], $route['middlewares']);

        self::assertCount(1, $pipelines->registeredStacks);
        self::assertSame('pipeline__' . md5(json_encode([])), $pipelines->registeredStacks[0]->getId());
    }

    public function testGroupCombinesPrefixAndMiddleware(): void
    {
        $container = new ArgonContainer();
        $container->tag(FirstMiddleware::class, ['middleware.http' => ['group' => ['api'], 'priority' => 20]]);
        $container->tag(SecondMiddleware::class, ['middleware.http' => ['group' => ['api'], 'priority' => 10]]);

        $routes = new RouteManager();
        $pipelines = new RecordingPipelineManager();
        $router = new Router($container, $routes, $pipelines);

        $router->group(['api'], '/admin', function (Router $router): void {
            $router->get('/dashboard', DummyRequestHandler::class, [ThirdMiddleware::class], 'admin.dashboard');
        });

        $registered = $routes->getRoutesFor('GET');
        self::assertArrayHasKey('/admin/dashboard', $registered);

        $route = $registered['/admin/dashboard'];
        self::assertSame('admin.dashboard', $route['name']);
        $expectedOrder = [
            ThirdMiddleware::class,
            FirstMiddleware::class,
            SecondMiddleware::class,
        ];

        self::assertSame('pipeline__' . md5(json_encode($expectedOrder)), $route['pipelineId']);
        self::assertSame($expectedOrder, $route['middlewares']);

        self::assertCount(1, $pipelines->registeredStacks);
        self::assertSame($expectedOrder, $pipelines->registeredStacks[0]->toArray());
    }

    public function testConvenienceMethodsRegisterRoutesWithCorrectVerbs(): void
    {
        $verbs = [
            'POST' => 'post',
            'PUT' => 'put',
            'PATCH' => 'patch',
            'DELETE' => 'delete',
            'OPTIONS' => 'options',
        ];

        foreach ($verbs as $verb => $method) {
            $container = new ArgonContainer();
            $routes = new RouteManager();
            $pipelines = new RecordingPipelineManager();
            $router = new Router($container, $routes, $pipelines);

            $path = '/' . strtolower($verb);
            $handler = 'Handler@' . strtolower($method);

            $router->$method($path, $handler);

            $registered = $routes->getRoutesFor($verb);
            self::assertArrayHasKey($path, $registered);

            $route = $registered[$path];
            self::assertSame($verb, $route['method']);
            self::assertSame($handler, $route['handler']);
            self::assertSame('pipeline__' . md5(json_encode([])), $route['pipelineId']);

            self::assertCount(1, $pipelines->registeredStacks);
            self::assertSame('pipeline__' . md5(json_encode([])), $pipelines->registeredStacks[0]->getId());
        }
    }

    public function testGroupAcceptsCommaSeparatedAliasMetadata(): void
    {
        $container = new ArgonContainer();
        $container->tag(StringAliasMiddleware::class, ['middleware.http' => ['group' => 'admin, api', 'priority' => 5]]);
        $container->tag(ExplicitMiddleware::class, ['middleware.http' => ['group' => ['admin'], 'priority' => 10]]);

        $routes = new RouteManager();
        $pipelines = new RecordingPipelineManager();
        $router = new Router($container, $routes, $pipelines);

        $router->group(['admin'], '/secure', function (Router $router): void {
            $router->get('/zone', DummyRequestHandler::class, [], 'secure.zone');
        });

        $registered = $routes->getRoutesFor('GET');
        $route = $registered['/secure/zone'];

        self::assertSame([
            ExplicitMiddleware::class,
            StringAliasMiddleware::class,
        ], $route['middlewares']);

        self::assertSame([
            ExplicitMiddleware::class,
            StringAliasMiddleware::class,
        ], $pipelines->registeredStacks[0]->toArray());
    }
}

final class RecordingPipelineManager implements PipelineManagerInterface
{
    /** @var list<MiddlewareStackInterface> */
    public array $registeredStacks = [];

    public function register(MiddlewareStackInterface $stack): void
    {
        $this->registeredStacks[] = $stack;
    }

    public function get(MiddlewareStackInterface|string $keyOrStack): RequestHandlerInterface
    {
        throw new \BadMethodCallException('Not implemented for tests.');
    }
}

final class DummyRequestHandler implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new \BadMethodCallException('Not used in tests.');
    }
}

final class FirstMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class SecondMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class ThirdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class StringAliasMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

final class ExplicitMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }
}

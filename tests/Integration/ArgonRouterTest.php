<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\ArgonRouter;
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

        $router = new ArgonRouter($container, $routes, $pipelines);
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
        $router = new ArgonRouter($container, $routes, $pipelines);

        $router->group(['api'], '/admin', function (ArgonRouter $router): void {
            $router->get('/dashboard', DummyRequestHandler::class, [ThirdMiddleware::class], 'admin.dashboard');
        });

        $registered = $routes->getRoutesFor('GET');
        self::assertArrayHasKey('/admin/dashboard', $registered);

        $route = $registered['/admin/dashboard'];
        self::assertSame('admin.dashboard', $route['name']);
        self::assertSame('pipeline__' . md5(json_encode([
            FirstMiddleware::class,
            SecondMiddleware::class,
            ThirdMiddleware::class,
        ])), $route['pipelineId']);
        self::assertSame([
            FirstMiddleware::class,
            SecondMiddleware::class,
            ThirdMiddleware::class,
        ], $route['middlewares']);

        self::assertCount(1, $pipelines->registeredStacks);
        self::assertSame([
            FirstMiddleware::class,
            SecondMiddleware::class,
            ThirdMiddleware::class,
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

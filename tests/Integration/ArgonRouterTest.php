<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\Middleware\DispatcherInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\RouteManager;
use PHPUnit\Framework\TestCase;

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
        self::assertSame(self::pipelineId([]), $route['pipelineId']);
        self::assertSame([], $route['middlewares']);

        self::assertCount(1, $pipelines->registeredStacks);
        self::assertSame(self::pipelineId([]), $pipelines->registeredStacks[0]->getId());
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

        self::assertSame(self::pipelineId($expectedOrder), $route['pipelineId']);
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
            $handler = 'Handler@' . $method;

            $router->$method($path, $handler);

            $registered = $routes->getRoutesFor($verb);
            self::assertArrayHasKey($path, $registered);

            $route = $registered[$path];
            self::assertSame($verb, $route['method']);
            self::assertSame($handler, $route['handler']);
            self::assertSame(self::pipelineId([]), $route['pipelineId']);

            self::assertCount(1, $pipelines->registeredStacks);
            self::assertSame(
                self::pipelineId([]),
                $pipelines->registeredStacks[0]->getId()
            );
        }
    }

    public function testGroupAcceptsCommaSeparatedAliasMetadata(): void
    {
        $container = new ArgonContainer();
        $container->tag(
            StringAliasMiddleware::class,
            ['middleware.http' => ['group' => 'admin, api', 'priority' => 5]]
        );
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

    public function testGroupExpandsMiddlewareInterfaceServiceIds(): void
    {
        $container = new ArgonContainer();
        $container->tag(DispatcherInterface::class, ['middleware.http' => ['group' => ['web'], 'priority' => 6000]]);

        $routes = new RouteManager();
        $pipelines = new RecordingPipelineManager();
        $router = new Router($container, $routes, $pipelines);

        $router->group(['web'], '', static function (Router $router): void {
            $router->get('/interface-middleware', DummyRequestHandler::class);
        });

        $registered = $routes->getRoutesFor('GET');
        self::assertSame([DispatcherInterface::class], $registered['/interface-middleware']['middlewares']);
        self::assertSame([DispatcherInterface::class], $pipelines->registeredStacks[0]->toArray());
    }

    public function testUnknownMiddlewareAliasFailsWithRoutingException(): void
    {
        $router = new Router(new ArgonContainer(), new RouteManager(), new RecordingPipelineManager());

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Unknown middleware service id or group alias [missing-alias].');

        $router->get('/missing-middleware', DummyRequestHandler::class, ['missing-alias']);
    }

    public function testInvalidMiddlewareDefinitionFailsWithRoutingException(): void
    {
        $router = new Router(new ArgonContainer(), new RouteManager(), new RecordingPipelineManager());

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Invalid middleware definition provided to router.');

        $router->get('/invalid-middleware', DummyRequestHandler::class, [new \stdClass()]);
    }

    /**
     * @param list<class-string> $middlewares
     */
    private static function pipelineId(array $middlewares): string
    {
        return (new MiddlewareStack($middlewares))->getId();
    }
}

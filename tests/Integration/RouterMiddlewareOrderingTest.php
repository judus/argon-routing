<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\RouteManager;
use Maduser\Argon\Routing\Router;
use Maduser\Argon\Routing\Middleware\RouteDispatcherMiddleware;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\HighPriorityRouteMiddleware;
use Maduser\Argon\Routing\Tests\Integration\Fixtures\LowPriorityRouteMiddleware;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

final class RouterMiddlewareOrderingTest extends TestCase
{
    public function testRouteSpecificMiddlewarePrecedesDispatcher(): void
    {
        $container = new ArgonContainer();

        $container->set(RouteDispatcherMiddleware::class)
            ->tag(['middleware.http' => ['priority' => 6000, 'group' => 'web']]);

        $routeManager = new RouteManager();
        $pipelineManager = new class implements PipelineManagerInterface {
            /** @var list<list<class-string>> */
            public array $recorded = [];

            #[\Override]
            public function register(MiddlewareStackInterface $stack): void
            {
                $this->recorded[] = $stack->toArray();
            }

            #[\Override]
            public function get(MiddlewareStackInterface|string $keyOrStack): RequestHandlerInterface
            {
                throw new \BadMethodCallException('Not required for this test.');
            }
        };

        $router = new Router($container, $routeManager, $pipelineManager);

        $router->get(
            '/posts/{id}/{cat}',
            'Controller@action',
            [
                HighPriorityRouteMiddleware::class => ['priority' => 6500],
                'web',
                LowPriorityRouteMiddleware::class => ['priority' => 5000],
            ]
        );

        self::assertNotEmpty($pipelineManager->recorded);
        self::assertSame(
            [
                HighPriorityRouteMiddleware::class,
                RouteDispatcherMiddleware::class,
                LowPriorityRouteMiddleware::class,
            ],
            $pipelineManager->recorded[0]
        );

        $routes = $routeManager->getRoutesFor('GET');
        self::assertArrayHasKey('/posts/{id}/{cat}', $routes);
        self::assertSame(
            [
                HighPriorityRouteMiddleware::class,
                RouteDispatcherMiddleware::class,
                LowPriorityRouteMiddleware::class,
            ],
            $routes['/posts/{id}/{cat}']['middlewares']
        );
    }
}

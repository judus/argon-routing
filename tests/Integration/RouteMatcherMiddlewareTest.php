<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Middleware\RouteMatcherMiddleware;
use Maduser\Argon\Routing\Route;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RouteMatcherMiddlewareTest extends TestCase
{
    public function testLogsMatchedRouteAndDelegatesRequest(): void
    {
        $route = new Route(
            method: 'GET',
            name: 'users.show',
            pattern: '/users/{id}',
            handler: 'UserController@show',
            middlewares: [FirstMiddleware::class],
            arguments: ['id' => '42'],
        );

        $matcher = new class ($route) implements RouteMatcherInterface {
            public function __construct(private Route $route)
            {
            }

            #[\Override]
            public function match(ServerRequestInterface $request): Route
            {
                return $this->route;
            }
        };

        $testHandler = new TestHandler();
        $logger = new Logger('tests');
        $logger->pushHandler($testHandler);

        $middleware = new RouteMatcherMiddleware($matcher, $logger);

        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/users/42');
        $handler = new class implements RequestHandlerInterface {
            public bool $handled = false;
            public ?ServerRequestInterface $request = null;

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handled = true;
                $this->request = $request;
                return (new Psr17Factory())->createResponse(200);
            }
        };

        $response = $middleware->process($request, $handler);

        self::assertTrue($handler->handled);
        self::assertInstanceOf(RouteInterface::class, $handler->request?->getAttribute(RouteInterface::class));
        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($testHandler->hasInfoThatContains('Route matched'));
    }
}

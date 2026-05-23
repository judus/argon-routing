<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Provider\MiddlewaresServiceProvider;
use Maduser\Argon\Middleware\Provider\RequestHandlerServiceProvider;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Provider\RouteServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class StackIntegrationTest extends TestCase
{
    public function testContainerBackedRoutesDispatchThroughMiddlewareResponders(): void
    {
        $container = $this->bootContainer();
        $router = $container->get(RouterInterface::class);

        $router->group(['web'], '', static function (RouterInterface $router): void {
            $router->get('/health', StackHealthController::class, [StackHeaderMiddleware::class], 'stack.health');
            $router->get('/hello/{name}', StackHelloController::class, [], 'stack.hello');
            $router->get('/text/{name}', StackTextController::class, [], 'stack.text');
        });

        $handler = $container->get(RequestHandlerInterface::class);
        $psr17 = new Psr17Factory();

        $health = $handler->handle($psr17->createServerRequest('GET', '/health'));
        self::assertSame(200, $health->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $health->getHeaderLine('Content-Type'));
        self::assertSame('routing', $health->getHeaderLine('X-Argon-Routing-Test'));
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","source":"container-store"}',
            (string) $health->getBody()
        );

        $hello = $handler->handle($psr17->createServerRequest('GET', '/hello/julien'));
        self::assertSame(200, $hello->getStatusCode());
        self::assertSame('application/json; charset=UTF-8', $hello->getHeaderLine('Content-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"message":"Hello julien","name":"julien"}',
            (string) $hello->getBody()
        );

        $text = $handler->handle($psr17->createServerRequest('GET', '/text/router'));
        self::assertSame(200, $text->getStatusCode());
        self::assertSame('text/plain; charset=UTF-8', $text->getHeaderLine('Content-Type'));
        self::assertSame('Plain text route for router', (string) $text->getBody());
    }

    private function bootContainer(): ArgonContainer
    {
        $container = new ArgonContainer();
        $container->set(LoggerInterface::class, NullLogger::class)->shared();
        $container->set(ResponseFactoryInterface::class, Psr17Factory::class)->shared();
        $container->set(StreamFactoryInterface::class, Psr17Factory::class)->shared();

        $container->register([
            RequestHandlerServiceProvider::class,
            MiddlewaresServiceProvider::class,
            RouteServiceProvider::class,
        ]);

        $container->boot();

        return $container;
    }
}

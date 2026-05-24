<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Provider\RequestHandlerServiceProvider;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Provider\RouteServiceProvider;
use Maduser\Argon\Support\Contracts\ResultResponderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class StackIntegrationTest extends TestCase
{
    public function testContainerBackedRoutesDelegateResultsToResponder(): void
    {
        $container = $this->bootContainer();
        $router = $container->get(RouterInterface::class);

        $router->group(['web'], '', static function (RouterInterface $router): void {
            $router->get('/health', StackHealthController::class, [StackHeaderMiddleware::class], 'stack.health');
            $router->get('/hello/{name}', StackHelloController::class, [], 'stack.hello');
            $router->get('/array-handler/{name}', [StackMethodController::class, 'show'], [], 'stack.array-handler');
            $router->get('/string-handler/{name}', StackMethodController::class . '@show', [], 'stack.string-handler');
            $router->get('/text/{name}', StackTextController::class, [], 'stack.text');
        });

        $handler = $container->get(RequestHandlerInterface::class);
        $psr17 = new Psr17Factory();

        $health = $handler->handle($psr17->createServerRequest('GET', '/health'));
        self::assertSame(200, $health->getStatusCode());
        self::assertSame('routing', $health->getHeaderLine('X-Argon-Routing-Test'));
        self::assertSame('array', $health->getHeaderLine('X-Argon-Result-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","source":"container-store"}',
            (string) $health->getBody()
        );

        $hello = $handler->handle($psr17->createServerRequest('GET', '/hello/julien'));
        self::assertSame(200, $hello->getStatusCode());
        self::assertSame('array', $hello->getHeaderLine('X-Argon-Result-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"message":"Hello julien","name":"julien"}',
            (string) $hello->getBody()
        );

        $arrayHandler = $handler->handle($psr17->createServerRequest('GET', '/array-handler/route'));
        self::assertSame(200, $arrayHandler->getStatusCode());
        self::assertSame('array', $arrayHandler->getHeaderLine('X-Argon-Result-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"handler":"method","name":"route"}',
            (string) $arrayHandler->getBody()
        );

        $stringHandler = $handler->handle($psr17->createServerRequest('GET', '/string-handler/cache'));
        self::assertSame(200, $stringHandler->getStatusCode());
        self::assertSame('array', $stringHandler->getHeaderLine('X-Argon-Result-Type'));
        self::assertJsonStringEqualsJsonString(
            '{"handler":"method","name":"cache"}',
            (string) $stringHandler->getBody()
        );

        $text = $handler->handle($psr17->createServerRequest('GET', '/text/router'));
        self::assertSame(200, $text->getStatusCode());
        self::assertSame('string', $text->getHeaderLine('X-Argon-Result-Type'));
        self::assertSame('Plain text route for router', (string) $text->getBody());
    }

    private function bootContainer(): ArgonContainer
    {
        $container = new ArgonContainer();
        $container->set(LoggerInterface::class, NullLogger::class)->shared();
        $container->set(ResultResponderInterface::class, StackResultResponder::class)->shared();

        $container->register([
            RequestHandlerServiceProvider::class,
            RouteServiceProvider::class,
        ]);

        $container->boot();

        return $container;
    }
}

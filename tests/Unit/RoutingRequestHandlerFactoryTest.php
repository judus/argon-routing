<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Unit;

use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Maduser\Argon\Routing\Factory\RoutingRequestHandlerFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RoutingRequestHandlerFactoryTest extends TestCase
{
    public function testDelegatesHandlingToResolvedHandler(): void
    {
        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('GET', '/items/42');
        $response = $psr17->createResponse(201);

        $resolvedHandler = new class($response) implements RequestHandlerInterface {
            public ?ServerRequestInterface $handledRequest = null;

            public function __construct(
                private readonly ResponseInterface $response,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handledRequest = $request;
                return $this->response;
            }
        };

        $resolver = new class($resolvedHandler) implements RequestHandlerResolverInterface {
            public ?ServerRequestInterface $resolvedWith = null;

            public function __construct(
                private readonly RequestHandlerInterface $handler,
            ) {
            }

            public function resolve(?ServerRequestInterface $request = null): RequestHandlerInterface
            {
                $this->resolvedWith = $request;
                return $this->handler;
            }
        };

        $factory = new RoutingRequestHandlerFactory($resolver);
        $handler = $factory->create();

        $result = $handler->handle($request);

        self::assertSame($response, $result);
        self::assertSame($request, $resolvedHandler->handledRequest);
        self::assertSame($request, $resolver->resolvedWith);
    }

    public function testHandlerWithoutSetRequestStillProcessesRequest(): void
    {
        $psr17 = new Psr17Factory();
        $request = $psr17->createServerRequest('POST', '/submit');
        $response = $psr17->createResponse(202);

        $resolvedHandler = new class($response) implements RequestHandlerInterface {
            public ?ServerRequestInterface $handledRequest = null;

            public function __construct(
                private readonly ResponseInterface $response,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->handledRequest = $request;
                return $this->response;
            }
        };

        $resolver = new class($resolvedHandler) implements RequestHandlerResolverInterface {
            public ?ServerRequestInterface $resolvedWith = null;

            public function __construct(
                private readonly RequestHandlerInterface $handler,
            ) {
            }

            public function resolve(?ServerRequestInterface $request = null): RequestHandlerInterface
            {
                $this->resolvedWith = $request;
                return $this->handler;
            }
        };

        $factory = new RoutingRequestHandlerFactory($resolver);
        $handler = $factory->create();

        $result = $handler->handle($request);

        self::assertSame($response, $result);
        self::assertSame($request, $resolvedHandler->handledRequest);
        self::assertSame($request, $resolver->resolvedWith);
    }
}

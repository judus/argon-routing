<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Routing\Exception\RouterException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RoutePipeline
{
    public function __construct(
        private ArgonContainer $container
    ) {
    }

    /**
     * @param list<class-string> $middleware
     */
    public function handle(
        array $middleware,
        callable $controller,
        ServerRequestInterface $request,
        RequestHandlerInterface $final
    ): ResponseInterface {
        $pipeline = $this->buildMiddlewareStack($middleware, $controller, $final);
        return $pipeline->handle($request);
    }

    /**
     * @param list<class-string> $middlewareClasses
     */
    private function buildMiddlewareStack(
        array $middlewareClasses,
        callable $controller,
        RequestHandlerInterface $final
    ): RequestHandlerInterface {
        $finalHandler = new class (Closure::fromCallable($controller), $final) implements RequestHandlerInterface {
            public function __construct(
                private readonly Closure $controller,
                private readonly RequestHandlerInterface $next
            ) {
            }

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $controller = $this->controller;
                return $this->next->handle(
                    $request->withAttribute('rawResult', $controller())
                );
            }
        };

        return array_reduce(
            array_reverse($middlewareClasses),
            function (RequestHandlerInterface $next, string $middlewareClass): RequestHandlerInterface {
                $middleware = $this->container->get($middlewareClass);

                if (!$middleware instanceof MiddlewareInterface) {
                    throw RouterException::forInvalidMiddlewareService($middlewareClass, get_debug_type($middleware));
                }

                return new class ($middleware, $next) implements RequestHandlerInterface {
                    public function __construct(
                        private readonly MiddlewareInterface $middleware,
                        private readonly RequestHandlerInterface $next
                    ) {
                    }

                    #[\Override]
                    public function handle(ServerRequestInterface $request): ResponseInterface
                    {
                        return $this->middleware->process($request, $this->next);
                    }
                };
            },
            $finalHandler
        );
    }
}

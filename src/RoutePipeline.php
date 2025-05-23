<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Container\ArgonContainer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

final readonly class RoutePipeline
{
    public function __construct(
        private ArgonContainer $container
    ) {
    }

    public function handle(array $middleware, callable $controller, ServerRequestInterface $request, RequestHandlerInterface $final): ResponseInterface
    {
        $pipeline = $this->buildMiddlewareStack($middleware, $controller, $final);
        return $pipeline->handle($request);
    }

    private function buildMiddlewareStack(array $middlewareClasses, callable $controller, RequestHandlerInterface $final): RequestHandlerInterface
    {
        $finalHandler = new class ($controller, $final) implements RequestHandlerInterface {
            public function __construct(
                private $controller,
                private RequestHandlerInterface $next
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $result = ($this->controller)();
                return $this->next->handle(
                    $request->withAttribute('rawResult', $result)
                );
            }
        };

        return array_reduce(
            array_reverse($middlewareClasses),
            fn(RequestHandlerInterface $next, string $middlewareClass) =>
            new class ($this->container->get($middlewareClass), $next) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $next
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            },
            $finalHandler
        );
    }
}

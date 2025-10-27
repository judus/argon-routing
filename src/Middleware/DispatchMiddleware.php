<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\Exceptions\NotFoundException;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\RoutePipeline;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class DispatchMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ArgonContainer $container,
        private RoutePipeline $pipeline,
    ) {
    }

    /**
     * @throws ContainerException
     * @throws NotFoundException
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var MatchedRouteInterface|null $route */
        $route = $request->getAttribute(MatchedRouteInterface::class);

        if (!$route instanceof MatchedRouteInterface) {
            throw RouterException::forMissingMatchedRoute();
        }

        $routeHandler = $route->getHandler();

        if ($routeHandler instanceof Closure) {
            // TODO: Future support for closure handlers
            throw RouterException::forUnsupportedClosureRouteHandler();
        }

        $serviceId = (string) $routeHandler;

        $invoker = $this->container->get($serviceId);

        if (!is_callable($invoker)) {
            $type = get_debug_type($invoker);
            throw RouterException::forNonCallableHandler($serviceId, $type);
        }

        if ($serviceId === DispatchMiddleware::class) {
            throw RouterException::forMiddlewareRecursion('DispatchMiddleware');
        }

        $callable = fn(): mixed => $invoker($route->getArguments());

        return $this->pipeline->handle(
            $route->getMiddlewares(),
            $callable,
            $request,
            $handler
        );
    }
}

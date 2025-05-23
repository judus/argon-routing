<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Closure;
use Maduser\Argon\Contracts\Support\ResultContextInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Support\ResultContext;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteContextInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final readonly class RouteDispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $container,
        private RouteContextInterface $context,
        private ResultContextInterface $result,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->context->getRoute();

        $handlerDef = $route->getHandler();

        if ($handlerDef instanceof Closure) {
            $invoker = $handlerDef;
        } else {
            $serviceId = (string) $handlerDef;

            if ($serviceId === self::class) {
                throw new RuntimeException('Infinite RouteDispatcherMiddleware loop detected.');
            }

            $invoker = $this->container->get($route->getPattern());

            if (!is_callable($invoker)) {
                $type = get_debug_type($invoker);
                throw new RuntimeException("Handler [$serviceId] is not callable (got: $type).");
            }
        }

        $args = $route->getArguments();

        $result = $invoker($args);

        $this->result->set($result);

        return $handler->handle($request);
    }
}

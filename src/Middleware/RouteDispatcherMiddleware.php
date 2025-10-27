<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Closure;
use Maduser\Argon\Middleware\Contracts\ResultContextInterface;
use Maduser\Argon\Routing\Contracts\RouteContextInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RouteDispatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ContainerInterface $container,
        private RouteContextInterface $context,
        private ResultContextInterface $result,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $this->context->getRoute();

        $handlerDef = $route->getHandler();

        if ($handlerDef instanceof Closure) {
            $invoker = $handlerDef;
        } else {
            $serviceId = (string) $handlerDef;

            if ($serviceId === self::class) {
                throw RouterException::forMiddlewareRecursion('RouteDispatcherMiddleware');
            }

            $invoker = $this->container->get($route->getPattern());

            if (!is_callable($invoker)) {
                $type = get_debug_type($invoker);
                throw RouterException::forNonCallableHandler($serviceId, $type);
            }
        }

        $args = $route->getArguments();

        $result = $invoker($args);

        $this->result->set($result);

        return $handler->handle($request);
    }
}

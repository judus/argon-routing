<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Closure;
use Maduser\Argon\Middleware\Contracts\ResultContextInterface;
use Maduser\Argon\Middleware\ResultContext;
use Maduser\Argon\Routing\Contracts\RouteInterface;
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
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[\Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        /** @var RouteInterface|null $route */
        $route = $request->getAttribute(RouteInterface::class);

        if (!$route instanceof RouteInterface) {
            throw RouterException::forMissingMatchedRoute();
        }

        $handlerDef = $route->getHandler();

        if ($handlerDef instanceof Closure) {
            $invoker = $handlerDef;
        } else {
            $method = null;

            if (is_array($handlerDef)) {
                if (!isset($handlerDef[0]) || !is_string($handlerDef[0])) {
                    throw RouterException::forMalformedHandlerDefinition($handlerDef);
                }

                $serviceId = $handlerDef[0];
                $method = isset($handlerDef[1]) ? (string) $handlerDef[1] : null;
            } else {
                $serviceId = $handlerDef;
            }

            if ($serviceId === self::class) {
                throw RouterException::forMiddlewareRecursion('RouteDispatcherMiddleware');
            }

            $resolved = $this->container->get($route->getPattern());

            if (!is_callable($resolved)) {
                $type = get_debug_type($resolved);
                throw RouterException::forNonCallableHandler($serviceId, $type, $method);
            }

            $invoker = $resolved;
        }

        $args = $route->getArguments();

        /** @var callable(array<int|string, string>): mixed $invoker */
        /** @var ResultContextInterface|null $context */
        $context = $request->getAttribute(ResultContextInterface::class);
        if (!$context instanceof ResultContextInterface) {
            $context = new ResultContext();
        }

        $context->set($invoker($args));

        return $handler->handle(
            $request->withAttribute(ResultContextInterface::class, $context)
        );
    }
}

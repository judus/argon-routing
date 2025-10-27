<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RouteMatcherMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RouteMatcherInterface $matcher,
        private LoggerInterface       $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute(RouteInterface::class);

        if (!$route instanceof RouteInterface) {
            $route = $this->matcher->match($request);

            $this->logger->info('Route matched', [
                'handler' => $route->getHandler(),
                'middlewares' => $route->getMiddlewares(),
                'arguments' => $route->getArguments(),
            ]);

            $request = $request->withAttribute(RouteInterface::class, $route);
        }

        return $handler->handle($request);
    }
}

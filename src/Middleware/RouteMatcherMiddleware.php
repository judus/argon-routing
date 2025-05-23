<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Monolog\Logger;
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
        $route = $this->matcher->match($request);

        $this->logger->info('Route matched', [
            'handler' => $route->getHandler(),
            'middlewares' => $route->getMiddlewares(),
            'arguments' => $route->getArguments(),
        ]);

        return $handler->handle($request);
    }
}

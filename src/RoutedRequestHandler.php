<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RoutedRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly RequestHandlerInterface $pipeline,
        private readonly RouteInterface $route
    ) {
    }

    #[\Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $requestWithRoute = $request->getAttribute(RouteInterface::class) instanceof RouteInterface
            ? $request
            : $request->withAttribute(RouteInterface::class, $this->route);

        return $this->pipeline->handle($requestWithRoute);
    }
}

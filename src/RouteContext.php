<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use JsonSerializable;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteContextInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class RouteContext implements RouteContextInterface, JsonSerializable
{
    private ?RouteInterface $route;

    public function __construct(
        private readonly RouteMatcherInterface $matcher,
        private readonly ServerRequestInterface $request,
        ?RouteInterface $route = null,
    ) {
        $this->route = $route;
    }

    public function getRoute(?ServerRequestInterface $request = null): RouteInterface
    {
        if ($this->route !== null) {
            return $this->route;
        }

        $this->route = $this->matcher->match($request ?? $this->request);

        return $this->route;
    }

    public function jsonSerialize(): array
    {
        return $this->route?->toArray() ?? [];
    }
}

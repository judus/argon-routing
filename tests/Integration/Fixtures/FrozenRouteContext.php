<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Fixtures;

use Maduser\Argon\Routing\Contracts\RouteContextInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Psr\Http\Message\ServerRequestInterface;

final class FrozenRouteContext implements RouteContextInterface
{
    public function __construct(
        private readonly RouteInterface $route
    ) {
    }

    public function getRoute(?ServerRequestInterface $request = null): RouteInterface
    {
        return $this->route;
    }
}

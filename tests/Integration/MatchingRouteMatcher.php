<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Route;
use Override;
use Psr\Http\Message\ServerRequestInterface;

final class MatchingRouteMatcher implements RouteMatcherInterface
{
    public function __construct(private Route $route)
    {
    }

    #[Override]
    public function match(ServerRequestInterface $request): Route
    {
        return $this->route;
    }
}

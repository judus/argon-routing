<?php

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use RuntimeException;

/**
 * Lightweight store used primarily for tests; kept around as a placeholder
 * should a standalone (non-container) variant ever materialise.
 */
final class InMemoryStore implements RouteStoreInterface
{
    private array $routes = [];

    public function all(string $method): array
    {
        return $this->routes[$method] ?? [];
    }

    public function add(RouteInterface $route): void
    {
        $this->routes[$route->getMethod()][$route->getPattern()] = $route->toArray();
    }
}

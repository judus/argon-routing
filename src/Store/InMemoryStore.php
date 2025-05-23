<?php

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use RuntimeException;

final class InMemoryStore implements RouteStoreInterface
{
    private array $routes = [];

    public function all(string $method): array
    {
        return $this->routes[$method] ?? [];
    }

    public function get(string $routeKey): array
    {
        foreach ($this->routes as $group) {
            if (isset($group[$routeKey])) {
                return $group[$routeKey];
            }
        }
        throw new RuntimeException("Route '{$routeKey}' not found.");
    }

    public function add(RouteInterface $route): void
    {
        $this->routes[$route->getMethod()][$route->getPattern()] = $route->toArray();
    }
}

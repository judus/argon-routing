<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;

/**
 * Lightweight store used primarily for tests; kept around as a placeholder
 * should a standalone (non-container) variant ever materialise.
 *
 * @psalm-import-type RouteArray from RouteInterface
 */
final class InMemoryStore implements RouteStoreInterface
{
    /** @var array<string, array<string, RouteArray>> */
    private array $routes = [];

    /** @inheritdoc */
    #[\Override]
    public function all(string $method): array
    {
        return $this->routes[$method] ?? [];
    }

    #[\Override]
    public function add(RouteInterface $route): void
    {
        $this->routes[$route->getMethod()][$route->getPattern()] = $route->toArray();
    }
}

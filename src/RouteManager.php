<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Store\InMemoryStore;

/**
 * @psalm-import-type RouteArray from RouteInterface
 */
final class RouteManager
{
    private RouteStoreInterface $store;

    public function __construct(
        ?RouteStoreInterface $store = null
    ) {
        $this->store = $store ?? new InMemoryStore();
    }

    /**
     * @param string $method
     * @return array<string, RouteArray>
     */
    public function getRoutesFor(string $method): array
    {
        return $this->store->all($method);
    }

    public function register(RouteInterface $route): void
    {
        $this->store->add($route);
    }
}

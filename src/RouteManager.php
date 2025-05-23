<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Store\InMemoryStore;
use Psr\Http\Server\MiddlewareInterface;

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
     * @return array<string, array{
     *      method: string,
     *      name?: string,
     *      pattern: string,
     *      compiled?: string,
     *      handler: class-string|array{0: class-string, 1: string}|Closure,
     *      pipelineId?: string,
     *      middlewares?: list<class-string<MiddlewareInterface>|MiddlewareInterface>
     *  }>
     */
    public function getRoutesFor(string $method): array
    {
        return $this->store->all($method);
    }

    public function getMeta(string $routeKey): array
    {
        return $this->store->get($routeKey);
    }

    public function register(RouteInterface $route): void
    {
        $this->store->add($route);
    }
}

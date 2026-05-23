<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

/**
 * @psalm-import-type RouteArray from RouteInterface
 */
interface RouteStoreInterface
{
    /**
     * @param string $method
     * @return array<string, RouteArray>
     */
    public function all(string $method): array;

    public function add(RouteInterface $route): void;
}

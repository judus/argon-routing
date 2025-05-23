<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Closure;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Psr\Http\Server\MiddlewareInterface;

interface RouteStoreInterface
{
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
    public function all(string $method): array;

    public function get(string $routeKey): array;

    public function add(RouteInterface $route): void;
}

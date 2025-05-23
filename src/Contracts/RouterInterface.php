<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Closure;
use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionException;

interface RouterInterface
{
    /**
     * @param class-string|array{0: class-string, 1: string}|Closure $handler
     */
    public function add(
        string $method,
        string $pattern,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function group(array $middleware, string $prefix, callable $callback): void;

    public function get(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function post(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function put(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function delete(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function patch(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    public function options(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;
}

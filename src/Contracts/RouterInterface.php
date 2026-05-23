<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Closure;

/**
 * @psalm-api
 */
interface RouterInterface
{
    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function add(
        string $method,
        string $pattern,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param array<int|string, mixed> $middleware
     */
    public function group(array $middleware, string $prefix, callable $callback): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */

    public function get(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function post(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function put(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function delete(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function patch(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    public function options(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void;
}

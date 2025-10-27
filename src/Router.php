<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;

final class Router implements RouterInterface
{
    private const ROUTE_LEVEL_PRIORITY = 6100;

    private ?PipelineManagerInterface $pipelines;
    private array $groupMiddleware = [];
    private string $groupPrefix = '';

    public function __construct(
        private readonly ArgonContainer $container,
        private readonly RouteManager $routes,
        ?PipelineManagerInterface $pipelines = null,
    ) {
        $this->pipelines = $pipelines;
    }

    /**
     * @param class-string|array{0: class-string, 1: string}|Closure $handler
     */
    public function add(
        string $method,
        string $pattern,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $method = strtoupper($method);
        $fullPath = rtrim($this->groupPrefix, '/') . '/' . ltrim($pattern, '/');

        $stack = $this->resolveMiddlewares([...$this->groupMiddleware, ...$middleware]);
        $compiled = $this->compileRoutePattern($fullPath);

        $route = new Route(
            method: $method,
            name: $name ?? $fullPath,
            pattern: $fullPath,
            handler: $handler,
            compiled: $compiled,
            pipelineId: $stack->getId(),
            middlewares: $stack->toArray(),
        );

        $this->routes->register($route);
        $this->pipelines?->register($stack);
    }

    private function compileRoutePattern(string $routePattern): string
    {
        $pattern = preg_replace_callback('~(/)?{(\w+)(?::([^}]+))?(\?)?}~', static function (array $m): string {
            $slash = $m[1] ?? '';
            $name = $m[2];
            $regex = isset($m[3]) && $m[3] !== '' ? $m[3] : '[^/]+';
            $optional = isset($m[4]);

            $segment = "$slash(?P<$name>$regex)";

            return $optional ? "(?:$segment)?" : $segment;
        }, rtrim($routePattern, '/'));

        return '#^' . $pattern . '/?$#';
    }

    private function resolveMiddlewares(array $middlewares): MiddlewareStackInterface
    {
        if ($middlewares === []) {
            return new MiddlewareStack([]);
        }

        $meta = $this->container->getTaggedMeta('middleware.http');
        $descriptors = $this->normaliseRouteMiddleware($middlewares, $meta);

        return $this->buildSortedStack($descriptors, $meta);
    }

    /**
     * @param array<int|string, mixed> $middlewares
     * @param array<string, array<string, mixed>> $meta
     * @return list<array{class: string, priority: int|null, index: int}>
     */
    private function normaliseRouteMiddleware(array $middlewares, array $meta): array
    {
        $normalized = [];
        $position = 0;

        foreach ($middlewares as $key => $entry) {
            if (is_string($key) && is_array($entry)) {
                $normalized[] = [
                    'class' => $key,
                    'priority' => array_key_exists('priority', $entry) ? (int) $entry['priority'] : null,
                    'index' => $position++,
                ];
                continue;
            }

            if (is_array($entry) && isset($entry['class']) && is_string($entry['class'])) {
                $normalized[] = [
                    'class' => $entry['class'],
                    'priority' => array_key_exists('priority', $entry) ? (int) $entry['priority'] : null,
                    'index' => $position++,
                ];
                continue;
            }

            if (!is_string($entry)) {
                throw new \InvalidArgumentException('Invalid middleware definition provided to router.');
            }

            $expanded = $this->expandAlias($entry, $meta);

            if ($expanded === null) {
                $normalized[] = [
                    'class' => $entry,
                    'priority' => null,
                    'index' => $position++,
                ];
                continue;
            }

            foreach ($expanded as $class) {
                $normalized[] = [
                    'class' => $class,
                    'priority' => null,
                    'index' => $position++,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, array<string, mixed>> $meta
     */
    private function expandAlias(string $alias, array $meta): ?array
    {
        $matches = [];

        foreach ($meta as $class => $attributes) {
            if (!isset($attributes['group'])) {
                continue;
            }

            $groups = is_array($attributes['group'])
                ? $attributes['group']
                : array_map('trim', explode(',', (string) $attributes['group']));

            if (in_array($alias, $groups, true)) {
                $matches[] = $class;
            }
        }

        return $matches === [] ? null : $matches;
    }

    /**
     * @param list<array{class: string, priority: int|null, index: int}|string> $middleware
     * @param array<string, array<string, mixed>> $meta
     */
    private function buildSortedStack(array $middleware, array $meta): MiddlewareStack
    {
        $withPriority = [];

        foreach ($middleware as $descriptor) {
            if (is_string($descriptor)) {
                $class = $descriptor;
                $priorityOverride = null;
                $index = count($withPriority);
            } else {
                $class = $descriptor['class'];
                $priorityOverride = $descriptor['priority'];
                $index = $descriptor['index'];
            }

            $priority = $priorityOverride
                ?? ($meta[$class]['priority'] ?? null)
                ?? self::ROUTE_LEVEL_PRIORITY;

            $withPriority[] = [
                'class' => $class,
                'priority' => (int) $priority,
                'index' => $index,
            ];
        }

        usort(
            $withPriority,
            static function (array $a, array $b): int {
                $cmp = $b['priority'] <=> $a['priority'];
                return $cmp !== 0 ? $cmp : $a['index'] <=> $b['index'];
            }
        );

        return new MiddlewareStack(array_column($withPriority, 'class'));
    }

    public function group(array $middleware, string $prefix, callable $callback): void
    {
        $previousPrefix = $this->groupPrefix;
        $previousMiddleware = $this->groupMiddleware;

        $this->groupPrefix = rtrim($previousPrefix . '/' . trim($prefix, '/'), '/');
        $this->groupMiddleware = [...$previousMiddleware, ...$middleware];

        $callback($this);

        $this->groupPrefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('GET', $path, $handler, $middleware, $name);
    }

    public function post(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('POST', $path, $handler, $middleware, $name);
    }

    public function put(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('PUT', $path, $handler, $middleware, $name);
    }

    public function patch(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('PATCH', $path, $handler, $middleware, $name);
    }

    public function delete(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    public function options(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('OPTIONS', $path, $handler, $middleware, $name);
    }
}

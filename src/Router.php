<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use Psr\Http\Server\MiddlewareInterface;

final class Router implements RouterInterface
{
    private const ROUTE_LEVEL_PRIORITY = 6100;

    private ?PipelineManagerInterface $pipelines;
    /** @var array<int|string, mixed> */
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
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
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

        if ($pattern === null) {
            throw new \LogicException('Route pattern compilation failed.');
        }

        return '#^' . $pattern . '/?$#';
    }

    /**
     * @param array<int|string, mixed> $middlewares
     */
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
     * @return list<array{class: class-string<MiddlewareInterface>, priority: int|null, index: int}>
     */
    private function normaliseRouteMiddleware(array $middlewares, array $meta): array
    {
        $normalized = [];
        $position = 0;

        foreach ($middlewares as $key => $entry) {
            if (is_string($key) && is_array($entry)) {
                $normalized[] = [
                    'class' => $this->middlewareServiceId($key),
                    'priority' => $this->priorityOverride($entry),
                    'index' => $position++,
                ];
                continue;
            }

            if (is_array($entry) && isset($entry['class']) && is_string($entry['class'])) {
                $normalized[] = [
                    'class' => $this->middlewareServiceId($entry['class']),
                    'priority' => $this->priorityOverride($entry),
                    'index' => $position++,
                ];
                continue;
            }

            if (!is_string($entry)) {
                throw RouterException::forInvalidMiddlewareDefinition();
            }

            $expanded = $this->expandAlias($entry, $meta);

            if ($expanded === null) {
                $normalized[] = [
                    'class' => $this->middlewareServiceId($entry),
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
     * @return list<class-string<MiddlewareInterface>>|null
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
                $matches[] = $this->middlewareServiceId($class);
            }
        }

        return $matches === [] ? null : $matches;
    }

    /**
     * @return class-string<MiddlewareInterface>
     */
    private function middlewareServiceId(string $class): string
    {
        if (!class_exists($class) && !interface_exists($class)) {
            throw RouterException::forUnknownMiddlewareServiceId($class);
        }

        if (!is_a($class, MiddlewareInterface::class, true)) {
            throw RouterException::forInvalidMiddlewareService($class, $class);
        }

        return $class;
    }

    /**
     * @param array<array-key, mixed> $attributes
     */
    private function priorityOverride(array $attributes): ?int
    {
        return array_key_exists('priority', $attributes) ? (int) $attributes['priority'] : null;
    }

    /**
     * @param list<array{class: class-string<MiddlewareInterface>, priority: int|null, index: int}> $middleware
     * @param array<string, array<string, mixed>> $meta
     */
    private function buildSortedStack(array $middleware, array $meta): MiddlewareStack
    {
        $withPriority = [];

        foreach ($middleware as $descriptor) {
            $class = $descriptor['class'];
            $priorityOverride = $descriptor['priority'];
            $index = $descriptor['index'];

            $priority = $priorityOverride
                ?? $this->priorityOverride($meta[$class] ?? [])
                ?? self::ROUTE_LEVEL_PRIORITY;

            $withPriority[] = [
                'class' => $class,
                'priority' => $priority,
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

        $classes = [];
        foreach ($withPriority as $middleware) {
            $classes[] = $middleware['class'];
        }

        return new MiddlewareStack($classes);
    }

    /**
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
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

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function get(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('GET', $path, $handler, $middleware, $name);
    }

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function post(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('POST', $path, $handler, $middleware, $name);
    }

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function put(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('PUT', $path, $handler, $middleware, $name);
    }

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function patch(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('PATCH', $path, $handler, $middleware, $name);
    }

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function delete(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    /**
     * @param string|array{0: string, 1?: string}|Closure $handler
     * @param array<int|string, mixed> $middleware
     */
    #[\Override]
    public function options(
        string $path,
        string|array|Closure $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        $this->add('OPTIONS', $path, $handler, $middleware, $name);
    }
}

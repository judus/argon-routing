<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Maduser\Argon\Routing\Contracts\RouterInterface;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use Closure;

final class ArgonRouter implements RouterInterface
{
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
            compiled: $compiled,
            handler: $handler,
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
        if (empty($middlewares)) {
            return new MiddlewareStack([]);
        }

        $meta = $this->container->getTaggedMeta('middleware.http');

        $expanded = $this->expandGroupAliases($middlewares, $meta);
        return $this->buildSortedStack($expanded, $meta);
    }

    private function expandGroupAliases(array $input, array $meta): array
    {
        $expanded = [];

        foreach ($input as $alias) {
            foreach ($meta as $class => $attributes) {
                $groups = [];

                if (isset($attributes['group'])) {
                    $groups = is_array($attributes['group'])
                        ? $attributes['group']
                        : array_map('trim', explode(',', (string) $attributes['group']));
                }

                if (in_array($alias, $groups, true)) {
                    $expanded[] = $class;
                }
            }
        }

        return $expanded !== [] ? array_unique($expanded) : $input;
    }

    private function buildSortedStack(array $middleware, array $meta): MiddlewareStack
    {
        $withPriority = [];

        foreach ($middleware as $class) {
            $priority = $meta[$class]['priority'] ?? 0;
            $withPriority[] = ['class' => $class, 'priority' => (int) $priority];
        }

        usort($withPriority, fn ($a, $b) => $b['priority'] <=> $a['priority']);

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

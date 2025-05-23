<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final readonly class RouteMatcher implements RouteMatcherInterface
{
    public function __construct(
        private RouteManager $routes,
    ) {
    }

    public function match(ServerRequestInterface $request): MatchedRouteInterface
    {
        $method = strtoupper($request->getMethod());
        $uri = $this->normalizeUri($request->getUri()->getPath());
        $routeMethodTag = "route.$method";

        foreach ($this->routes->getRoutesFor($routeMethodTag) as $pattern => $meta) {
            $compiled = $meta['compiled'] ?? $pattern;

            if (!empty($compiled) && preg_match($compiled, $uri, $matches)) {
                return new Route(
                    method: $method,
                    name: $meta['name'] ?? $pattern,
                    pattern: $pattern,
                    compiled: $compiled,
                    handler: $meta['handler'],
                    pipelineId: $meta['pipelineId'] ?? null,
                    middlewares: $meta['middlewares'] ?? [],
                    arguments: $this->extractParams($matches),
                );
            }
        }

        throw new RuntimeException("No route matched: " . strtoupper($method) . " $uri");
    }

    private function normalizeUri(string $uri): string
    {
        return '/' . ltrim(preg_replace('#^/?index\.php#', '', $uri) ?? $uri, '/');
    }

    /**
     * @param array<int|string, string> $matches
     * @return array<int|string, string>
     */
    private function extractParams(array $matches): array
    {
        return array_filter(
            $matches,
            static fn(string|int $key): bool => is_string($key),
            ARRAY_FILTER_USE_KEY
        );
    }
}

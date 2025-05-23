<?php

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use RuntimeException;

final class FileSystemStore implements RouteStoreInterface
{
    public function __construct(
        private readonly string $filePath
    ) {}

    public function all(string $method): array
    {
        $routes = $this->load();
        return $routes[strtolower($method)] ?? [];
    }

    public function get(string $routeKey): array
    {
        $routes = $this->load();

        foreach ($routes as $group) {
            if (isset($group[$routeKey])) {
                return $group[$routeKey];
            }
        }

        throw new RuntimeException("Route '{$routeKey}' not found in cache.");
    }

    public function add(RouteInterface $route): void
    {
        $routes = $this->load();
        $routes[strtolower($route->getMethod())][$route->getPattern()] = $route->toArray();
        $this->persist($routes);
    }

    private function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $data = include $this->filePath;

        return is_array($data) ? $data : [];
    }

    private function persist(array $routes): void
    {
        $export = var_export($routes, true);
        file_put_contents($this->filePath, "<?php return {$export};\n");
    }
}

<?php

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;

/**
 * Simple file-backed store reserved for potential standalone usage;
 * not required when operating inside the Argon container stack.
 */
final readonly class FileSystemStore implements RouteStoreInterface
{
    public function __construct(
        private string $filePath
    ) {
    }

    public function all(string $method): array
    {
        $routes = $this->load();
        return $routes[strtolower($method)] ?? [];
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
        file_put_contents($this->filePath, "<?php return $export;\n");
    }
}

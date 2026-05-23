<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;

/**
 * Simple file-backed store reserved for potential standalone usage;
 * not required when operating inside the Argon container stack.
 *
 * @psalm-import-type RouteArray from RouteInterface
 */
final readonly class FileSystemStore implements RouteStoreInterface
{
    public function __construct(
        private string $filePath
    ) {
    }

    /** @inheritdoc */
    #[\Override]
    public function all(string $method): array
    {
        $routes = $this->load();
        return $routes[strtolower($method)] ?? [];
    }

    #[\Override]
    public function add(RouteInterface $route): void
    {
        $routes = $this->load();
        $routes[strtolower($route->getMethod())][$route->getPattern()] = $route->toArray();
        $this->persist($routes);
    }

    /**
     * @return array<string, array<string, RouteArray>>
     */
    private function load(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        /** @psalm-suppress UnresolvableInclude Dynamic route cache file. */
        $data = include $this->filePath;

        if (!is_array($data)) {
            return [];
        }

        /** @var array<string, array<string, RouteArray>> $data */
        return $data;
    }

    /**
     * @param array<string, array<string, RouteArray>> $routes
     */
    private function persist(array $routes): void
    {
        $export = var_export($routes, true);
        file_put_contents($this->filePath, "<?php return $export;\n");
    }
}

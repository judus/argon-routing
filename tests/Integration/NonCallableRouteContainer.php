<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Override;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class NonCallableRouteContainer implements ContainerInterface
{
    public function __construct(private readonly string $routeId)
    {
    }

    #[Override]
    public function get(string $id): mixed
    {
        if ($id === $this->routeId) {
            return 'not-callable';
        }

        throw new RuntimeException("Service [$id] not mocked.");
    }

    #[Override]
    public function has(string $id): bool
    {
        return $id === $this->routeId;
    }
}

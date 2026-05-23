<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Fixtures;

use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * Minimal container double used by integration tests.
 *
 * @internal Only for test fixtures.
 */
final class RecordingContainer implements ContainerInterface
{
    /** @var array<string, callable> */
    private array $services;

    /** @var list<string> */
    public array $requestedIds = [];

    /**
     * @param array<string, callable> $services
     */
    public function __construct(array $services)
    {
        $this->services = $services;
    }

    #[\Override]
    public function get(string $id): callable
    {
        $this->requestedIds[] = $id;

        if (!isset($this->services[$id])) {
            throw new RuntimeException("Service [$id] not found.");
        }

        return $this->services[$id];
    }

    #[\Override]
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Store\ContainerStore;
use PHPUnit\Framework\TestCase;
use ReflectionException;
use RuntimeException;

final class ContainerStoreTest extends TestCase
{
    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function testAddRegistersRouteMetadataInContainer(): void
    {
        $container = new ArgonContainer();
        $store = new ContainerStore($container);

        $route = new Route(
            method: 'GET',
            name: 'users.show',
            pattern: '/users/{id}',
            handler: [TestContainerStoreController::class, 'handle'],
            compiled: '#^/users/(?P<id>[^/]+)/?$#',
            pipelineId: 'pipeline__xyz',
            middlewares: ['auth'],
        );

        $store->add($route);

        $all = $store->all('GET');
        self::assertArrayHasKey('/users/{id}', $all);
        self::assertSame('users.show', $all['/users/{id}']['name']);

    }

    public function testAddRejectsClosureHandlers(): void
    {
        $container = new ArgonContainer();
        $store = new ContainerStore($container);

        $route = new Route(
            method: 'GET',
            name: 'closure.route',
            pattern: '/closure',
            handler: static fn() => 'nope',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Closure handlers are not supported in container-based routes.');

        $store->add($route);
    }
}

final class TestContainerStoreController
{
    public function handle(string $id): string
    {
        return $id;
    }
}

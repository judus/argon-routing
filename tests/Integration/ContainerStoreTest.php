<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Routing\Exception\RouterException;
use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Store\ContainerStore;
use PHPUnit\Framework\TestCase;
use ReflectionException;

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

        $this->expectException(RouterException::class);
        $this->expectExceptionMessage('Closure handlers are not supported in container-based routes.');

        $store->add($route);
    }

    public function testAddSupportsInvokableClassHandlers(): void
    {
        $container = new ArgonContainer();
        $store = new ContainerStore($container);

        $route = new Route(
            method: 'POST',
            name: 'invoke.example',
            pattern: '/invoke/{id}',
            handler: InvokableRouteController::class,
            compiled: '#^/invoke/(?P<id>[^/]+)$#',
        );

        $store->add($route);

        $meta = $container->getDescriptor(InvokableRouteController::class);
        self::assertNotNull($meta);
        $invocation = $meta->getInvocation('__invoke');
        self::assertArrayHasKey('id', $invocation);
        self::assertNull($invocation['id']);
    }

    public function testAddReusesExistingDescriptor(): void
    {
        $container = new ArgonContainer();
        $container->set(ExistingContainerController::class);

        $store = new ContainerStore($container);

        $route = new Route(
            method: 'PUT',
            name: 'existing',
            pattern: '/existing/{id}',
            handler: [ExistingContainerController::class, 'handle'],
            compiled: '#^/existing/(?P<id>[^/]+)$#',
        );

        $store->add($route);

        $descriptor = $container->getDescriptor(ExistingContainerController::class);
        self::assertNotNull($descriptor);
        $invocation = $descriptor->getInvocation('handle');
        self::assertArrayHasKey('id', $invocation);
        self::assertNull($invocation['id']);
    }
}

final class TestContainerStoreController
{
    public function handle(string $id): string
    {
        return $id;
    }
}

final class InvokableRouteController
{
    public function __invoke(string $id): string
    {
        return $id;
    }
}

final class ExistingContainerController
{
    public function handle(string $id): string
    {
        return $id;
    }
}

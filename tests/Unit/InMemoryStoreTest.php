<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Unit;

use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Store\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class InMemoryStoreTest extends TestCase
{
    public function testAddStoresRouteData(): void
    {
        $store = new InMemoryStore();
        $route = new Route(
            method: 'GET',
            name: 'home',
            pattern: '/',
            handler: 'HomeController@index',
        );

        $store->add($route);

        $routes = $store->all('GET');
        self::assertArrayHasKey('/', $routes);
        self::assertSame('home', $routes['/']['name']);
    }
}

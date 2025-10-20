<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Routing\Route;
use Maduser\Argon\Routing\Store\FileSystemStore;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FileSystemStoreTest extends TestCase
{
    private string $cacheFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'argon-routes-') ?: sys_get_temp_dir() . '/argon-routes.php';
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testAddPersistsRoutesToDisk(): void
    {
        $store = new FileSystemStore($this->cacheFile);

        $route = new Route(
            method: 'GET',
            name: 'users.show',
            pattern: '/users/{id}',
            handler: 'UserController@show',
            compiled: '#^/users/(?P<id>[^/]+)/?$#',
            pipelineId: 'pipeline__abc',
            middlewares: ['auth'],
        );

        $store->add($route);

        self::assertFileExists($this->cacheFile);
        $data = include $this->cacheFile;
        self::assertArrayHasKey('get', $data);
        self::assertArrayHasKey('/users/{id}', $data['get']);
        self::assertSame('UserController@show', $data['get']['/users/{id}']['handler']);

        $routes = $store->all('GET');
        self::assertArrayHasKey('/users/{id}', $routes);
        self::assertSame('users.show', $routes['/users/{id}']['name']);

    }
}

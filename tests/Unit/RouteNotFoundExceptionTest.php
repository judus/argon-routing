<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Unit;

use Maduser\Argon\Routing\Exception\RouteNotFoundException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouteNotFoundExceptionTest extends TestCase
{
    public function testMessageCodeAndPreviousAreSet(): void
    {
        $previous = new RuntimeException('boom');

        $exception = new RouteNotFoundException('get', '/users/{id}', $previous);

        self::assertSame('No route matched: GET /users/{id}', $exception->getMessage());
        self::assertSame(404, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }
}


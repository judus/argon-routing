<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Exceptions;

use Maduser\Argon\Contracts\ErrorHandling\Http\HttpExceptionInterface;
use RuntimeException;
use Throwable;

final class RouteNotFoundException extends RuntimeException implements HttpExceptionInterface
{
    public function __construct(string $method, string $uri, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('No route matched: %s %s', strtoupper($method), $uri), 404, $previous);
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}

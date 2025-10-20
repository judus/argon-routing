<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Exceptions;

use RuntimeException;
use Throwable;

final class RouteNotFoundException extends RuntimeException
{
    public function __construct(string $method, string $uri, ?Throwable $previous = null)
    {
        parent::__construct(sprintf('No route matched: %s %s', strtoupper($method), $uri), 404, $previous);
    }
}

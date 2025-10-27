<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Exception;

use RuntimeException;

final class RouterException extends RuntimeException
{
    public static function forMissingCompiledPattern(): self
    {
        return new self('Route has no compiled pattern');
    }

    public static function forMissingMatchedRoute(): self
    {
        return new self('No resolved route found in request.');
    }

    public static function forUnsupportedClosureRouteHandler(): self
    {
        return new self('Closure route handlers are not yet supported.');
    }

    public static function forUnsupportedClosureInContainerStore(): self
    {
        return new self('Closure handlers are not supported in container-based routes.');
    }

    public static function forNonCallableHandler(string $serviceId, string $type): self
    {
        return new self("Handler [$serviceId] is not callable (got: $type).");
    }

    public static function forMiddlewareRecursion(string $middlewareName): self
    {
        return new self("Infinite $middlewareName loop detected.");
    }
}


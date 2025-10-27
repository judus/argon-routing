<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Exception;

use RuntimeException;
use function json_encode;

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

    public static function forNonCallableHandler(string $serviceId, string $type, ?string $method = null): self
    {
        $label = $method !== null ? $serviceId . '::' . $method : $serviceId;

        return new self("Handler [$label] is not callable (got: $type).");
    }

    public static function forMiddlewareRecursion(string $middlewareName): self
    {
        return new self("Infinite $middlewareName loop detected.");
    }

    /**
     * @param array<array-key, mixed> $handler
     */
    public static function forMalformedHandlerDefinition(array $handler): self
    {
        return new self(
            sprintf(
                'Malformed handler definition [%s]; expected [class-string, method-name].',
                json_encode($handler, JSON_THROW_ON_ERROR)
            )
        );
    }
}

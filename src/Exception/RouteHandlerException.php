<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Exception;

use RuntimeException;
use Throwable;

final class RouteHandlerException extends RuntimeException
{
    public static function forPreparationFailure(
        string $pattern,
        string $class,
        string $method,
        Throwable $previous
    ): self {
        $label = $class . '::' . $method;

        return new self(
            sprintf(
                'Unable to prepare handler [%s] for route [%s]: %s',
                $label,
                $pattern,
                $previous->getMessage()
            ),
            previous: $previous
        );
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final readonly class StackTextController
{
    public function __invoke(string $name): string
    {
        return 'Plain text route for ' . $name;
    }
}

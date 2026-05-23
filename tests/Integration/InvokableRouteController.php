<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class InvokableRouteController
{
    public function __invoke(string $id): string
    {
        return $id;
    }
}

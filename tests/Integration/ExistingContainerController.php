<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class ExistingContainerController
{
    public function handle(string $id): string
    {
        return $id;
    }
}

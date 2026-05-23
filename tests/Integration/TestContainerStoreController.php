<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class TestContainerStoreController
{
    public function handle(string $id): string
    {
        return $id;
    }
}

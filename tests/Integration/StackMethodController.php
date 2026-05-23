<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final readonly class StackMethodController
{
    /**
     * @return array{handler: string, name: string}
     */
    public function show(string $name): array
    {
        return [
            'handler' => 'method',
            'name' => $name,
        ];
    }
}

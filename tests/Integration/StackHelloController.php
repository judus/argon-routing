<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final readonly class StackHelloController
{
    /**
     * @return array{message: string, name: string}
     */
    public function __invoke(string $name): array
    {
        return [
            'message' => 'Hello ' . $name,
            'name' => $name,
        ];
    }
}

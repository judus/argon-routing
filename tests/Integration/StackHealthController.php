<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final readonly class StackHealthController
{
    /**
     * @return array{status: string, source: string}
     */
    public function __invoke(): array
    {
        return [
            'status' => 'ok',
            'source' => 'container-store',
        ];
    }
}

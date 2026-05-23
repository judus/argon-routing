<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class InvokeArrayController
{
    /**
     * @param array<string, string> $payload
     */
    public function __invoke(array $payload): string
    {
        return 'invoke:' . ($payload['slug'] ?? 'missing');
    }
}

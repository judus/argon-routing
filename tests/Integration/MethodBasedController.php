<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class MethodBasedController
{
    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    public function show(string $id): string
    {
        $this->trace->add('method.show');
        return 'method:' . $id;
    }
}

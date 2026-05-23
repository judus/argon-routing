<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class CallTrace
{
    /** @var list<string> */
    public array $events = [];

    public function add(string $event): void
    {
        $this->events[] = $event;
    }
}

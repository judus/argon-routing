<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

final class RecordingController
{
    /** @var list<array{args: array<string, string>}> */
    public array $calls = [];

    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(array $args): string
    {
        $this->trace->add('controller');
        $this->calls[] = ['args' => $args];
        return 'result:' . ($args['id'] ?? 'missing');
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use BadMethodCallException;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Override;
use Psr\Http\Server\RequestHandlerInterface;

final class RecordingPipelineManager implements PipelineManagerInterface
{
    /** @var list<MiddlewareStackInterface> */
    public array $registeredStacks = [];

    #[Override]
    public function register(MiddlewareStackInterface $stack): void
    {
        $this->registeredStacks[] = $stack;
    }

    #[Override]
    public function get(MiddlewareStackInterface|string $keyOrStack): RequestHandlerInterface
    {
        throw new BadMethodCallException('Not implemented for tests.');
    }
}

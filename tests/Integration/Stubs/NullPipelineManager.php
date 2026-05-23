<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Stubs;

use BadMethodCallException;
use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineManagerInterface;
use Override;
use Psr\Http\Server\RequestHandlerInterface;

final class NullPipelineManager implements PipelineManagerInterface
{
    #[Override]
    public function register(MiddlewareStackInterface $stack): void
    {
        // no-op for tests
    }

    #[Override]
    public function get(MiddlewareStackInterface|string $keyOrStack): RequestHandlerInterface
    {
        throw new BadMethodCallException('Not required in tests.');
    }
}

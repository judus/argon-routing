<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;
use Maduser\Argon\Middleware\Contracts\PipelineStoreInterface;
use Override;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

final class RecordingPipelineStore implements PipelineStoreInterface
{
    /** @var array<string, RequestHandlerInterface> */
    private array $pipelines;

    /** @var list<string|MiddlewareStackInterface> */
    public array $requested = [];

    /** @param array<string, RequestHandlerInterface> $pipelines */
    public function __construct(array $pipelines)
    {
        $this->pipelines = $pipelines;
    }

    #[Override]
    public function get($keyOrStack): RequestHandlerInterface
    {
        $this->requested[] = $keyOrStack;

        $key = $keyOrStack instanceof MiddlewareStackInterface
            ? $keyOrStack->getId()
            : $keyOrStack;

        if (!isset($this->pipelines[$key])) {
            throw new RuntimeException("Pipeline [$key] not found.");
        }

        return $this->pipelines[$key];
    }

    #[Override]
    public function register(MiddlewareStackInterface $stack): self
    {
        $this->pipelines[$stack->getId()] = new RecordingHandler();
        return $this;
    }
}

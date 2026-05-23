<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RecordingFinalHandler implements RequestHandlerInterface
{
    public ?ServerRequestInterface $handledRequest = null;

    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->handledRequest = $request;
        return $this->response;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}

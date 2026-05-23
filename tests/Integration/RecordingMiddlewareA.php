<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RecordingMiddlewareA implements MiddlewareInterface
{
    public function __construct(
        private readonly CallTrace $trace
    ) {
    }

    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->trace->add('A:before');
        $response = $handler->handle($request);
        $this->trace->add('A:after');
        return $response;
    }
}

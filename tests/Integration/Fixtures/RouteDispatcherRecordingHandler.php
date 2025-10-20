<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Fixtures;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Simple request handler that records the provided response.
 *
 * @internal Only for test fixtures.
 */
final class RouteDispatcherRecordingHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly ResponseInterface $response
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->response;
    }
}

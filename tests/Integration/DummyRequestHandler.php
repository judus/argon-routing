<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use BadMethodCallException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class DummyRequestHandler implements RequestHandlerInterface
{
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new BadMethodCallException('Not used in tests.');
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RecordingHandler implements RequestHandlerInterface
{
    public bool $handled = false;
    public ?ServerRequestInterface $request = null;

    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $psr17 = new Psr17Factory();
        $this->handled = true;
        $this->request = $request;

        return $psr17->createResponse(200);
    }
}

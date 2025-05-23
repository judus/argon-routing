<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class ResolvedRequestHandler
{
    public function __construct(
        private RequestHandlerInterface $handler,
        private ServerRequestInterface $request,
    ) {
    }

    public function getHandler(): RequestHandlerInterface
    {
        return $this->handler;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}

<?php
 namespace Maduser\Argon\Routing\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface RouteMatcherInterface
{
    public function match(ServerRequestInterface $request): RouteInterface;
}

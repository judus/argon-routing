<?php

namespace Maduser\Argon\Routing\Contracts;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface RequestHandlerResolverInterface
{
    public function resolve(?ServerRequestInterface $request = null): RequestHandlerInterface;
}
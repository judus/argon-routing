<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Psr\Http\Message\ServerRequestInterface;

interface RouteContextInterface
{
    public function getRoute(?ServerRequestInterface $request = null): RouteInterface;
}

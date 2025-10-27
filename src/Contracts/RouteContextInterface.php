<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Psr\Http\Message\ServerRequestInterface;

/**
 * @deprecated Route context is now stored on the PSR-7 request under
 *             `RouteInterface::class`. This interface remains for backward
 *             compatibility with older integrations.
 */
interface RouteContextInterface
{
    public function getRoute(?ServerRequestInterface $request = null): RouteInterface;
}

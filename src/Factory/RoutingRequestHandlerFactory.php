<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Factory;

use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RoutingRequestHandlerFactory
{
    public function __construct(
        private readonly RequestHandlerResolverInterface $resolver
    ) {
    }

    public function create(): RequestHandlerInterface
    {
        $resolver = $this->resolver;

        return new class($resolver) implements RequestHandlerInterface {
            public function __construct(
                private RequestHandlerResolverInterface $resolver
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $handler = $this->resolver->resolve($request);

                if (method_exists($handler, 'setRequest')) {
                    $handler->setRequest($request);
                }

                return $handler->handle($request);
            }
        };
    }
}

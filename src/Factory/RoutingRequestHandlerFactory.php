<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Factory;

use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class RoutingRequestHandlerFactory
{
    public function __construct(
        private RequestHandlerResolverInterface $resolver
    ) {
    }

    public function create(): RequestHandlerInterface
    {
        $resolver = $this->resolver;

        return new class ($resolver) implements RequestHandlerInterface {
            public function __construct(
                private readonly RequestHandlerResolverInterface $resolver
            ) {
            }

            #[\Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $handler = $this->resolver->resolve($request);

                return $handler->handle($request);
            }
        };
    }
}

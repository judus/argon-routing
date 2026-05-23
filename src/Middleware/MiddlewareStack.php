<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Middleware;

use Maduser\Argon\Middleware\Contracts\MiddlewareStackInterface;

final readonly class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * @param list<class-string> $middlewares
     */
    public function __construct(
        private array $middlewares = []
    ) {
    }

    /**
     * A deterministic, order-sensitive hash key.
     */
    #[\Override]
    public function getId(): string
    {
        return 'pipeline__' . md5(json_encode($this->middlewares, JSON_THROW_ON_ERROR));
    }

    /**
     * @return list<class-string>
     */
    #[\Override]
    public function toArray(): array
    {
        return $this->middlewares;
    }
}

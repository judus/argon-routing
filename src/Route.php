<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use LogicException;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Psr\Http\Server\MiddlewareInterface;

final class Route implements RouteInterface, MatchedRouteInterface
{
    /**
     * @param class-string|array{0: class-string, 1: string}|Closure $handler
     * @param list<class-string<MiddlewareInterface>|MiddlewareInterface> $middlewares
     * @param array<int|string, string> $arguments
     */
    public function __construct(
        private readonly string $method,
        private readonly string $name,
        private readonly string $pattern,
        private ?string $compiled = null,
        private readonly string|array|Closure $handler,
        private ?string $pipelineId = null,
        private array $middlewares = [],
        private array $arguments = [],
    ) {
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setCompiled(string $pattern): void
    {
        $this->compiled = $pattern;
    }

    public function getCompiled(): string
    {
        return $this->compiled ?? throw new LogicException('Route has no compiled pattern');
    }

    public function getHandler(): string|array|Closure
    {
        return $this->handler;
    }

    public function setPipelineId(?string $pipelineId): void
    {
        $this->pipelineId = $pipelineId;
    }

    public function getPipelineId(): ?string
    {
        return $this->pipelineId;
    }

    /** @inheritdoc  */
    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    /** @inheritdoc  */
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @para array<int|string, string> $args
     */
    public function setArguments(array $args): void
    {
        $this->arguments = $args;
    }

    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function toArray(): array
    {
        return [
            'method' => $this->method,
            'name' => $this->name,
            'pattern' => $this->pattern,
            'compiled' => $this->compiled,
            'handler' => $this->stringifyHandler(),
            'pipelineId' => $this->pipelineId,
            'middlewares' => $this->middlewares,
            'arguments' => $this->arguments,
        ];
    }

    private function stringifyHandler(): string
    {
        return match (true) {
            is_array($this->handler) => implode('@', $this->handler),
            is_string($this->handler) => $this->handler,
            $this->handler instanceof Closure => 'Closure<' . spl_object_id($this->handler) . '>',
            default => 'UnknownHandler',
        };
    }
}

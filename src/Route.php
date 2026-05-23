<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Closure;
use Maduser\Argon\Routing\Contracts\MatchedRouteInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Exception\RouterException;

/**
 * @psalm-import-type RouteArray from RouteInterface
 * @psalm-import-type RouteHandler from RouteInterface
 */
final class Route implements RouteInterface, MatchedRouteInterface
{
    /**
     * @param RouteHandler $handler
     * @param list<class-string> $middlewares
     * @param array<int|string, string> $arguments
     */
    public function __construct(
        private readonly string $method,
        private readonly string $name,
        private readonly string $pattern,
        private readonly string|array|Closure $handler,
        private ?string $compiled = null,
        private ?string $pipelineId = null,
        private array $middlewares = [],
        private array $arguments = [],
    ) {
    }

    #[\Override]
    public function getName(): ?string
    {
        return $this->name;
    }

    #[\Override]
    public function getPattern(): string
    {
        return $this->pattern;
    }

    #[\Override]
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
        return $this->compiled ?? throw RouterException::forMissingCompiledPattern();
    }

    /**
     * @return RouteHandler
     */
    #[\Override]
    public function getHandler(): string|array|Closure
    {
        return $this->handler;
    }

    #[\Override]
    public function setPipelineId(?string $pipelineId): void
    {
        $this->pipelineId = $pipelineId;
    }

    #[\Override]
    public function getPipelineId(): ?string
    {
        return $this->pipelineId;
    }

    /** @inheritdoc  */
    #[\Override]
    public function setMiddlewares(array $middlewares): void
    {
        $this->middlewares = $middlewares;
    }

    /** @inheritdoc  */
    #[\Override]
    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    /**
     * @param array<int|string, string> $args
     */
    #[\Override]
    public function setArguments(array $args): void
    {
        $this->arguments = $args;
    }

    /**
     * @return array<int|string, string>
     */
    #[\Override]
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return RouteArray
     */
    #[\Override]
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
            is_array($this->handler) => $this->stringifyArrayHandler($this->handler),
            is_string($this->handler) => $this->handler,
            $this->handler instanceof Closure => 'Closure<' . spl_object_id($this->handler) . '>',
            default => 'UnknownHandler',
        };
    }

    /**
     * @param array<array-key, mixed> $handler
     */
    private function stringifyArrayHandler(array $handler): string
    {
        $class = $handler[0] ?? 'UnknownHandler';

        if (!is_string($class)) {
            return 'UnknownHandler';
        }

        if (!isset($handler[1]) || !is_string($handler[1])) {
            return $class;
        }

        return $class . '@' . $handler[1];
    }
}

<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Contracts;

use Closure;
use Psr\Http\Server\MiddlewareInterface;

/**
 * @psalm-type RouteHandler = string|array<array-key, mixed>|Closure
 * @psalm-type RouteArray = array{
 *     method: string,
 *     name: string,
 *     pattern: string,
 *     compiled: ?string,
 *     handler: string,
 *     pipelineId: ?string,
 *     middlewares: list<class-string<MiddlewareInterface>>,
 *     arguments: array<int|string, string>
 * }
 */
interface RouteInterface
{
    public function getName(): ?string;

    public function getPattern(): string;

    public function getMethod(): string;

    /**
     * @return RouteHandler
     */
    public function getHandler(): string|array|Closure;

    public function setPipelineId(?string $pipelineId): void;

    public function getPipelineId(): ?string;

    /**
     * @param list<class-string<MiddlewareInterface>> $middlewares
     * @return void
     */
    public function setMiddlewares(array $middlewares): void;

    /**
     * @return list<class-string<MiddlewareInterface>>
     */
    public function getMiddlewares(): array;

    /**
     * @param array<int|string, string> $args
     * @return void
     */
    public function setArguments(array $args): void;

    /**
     * @return array<int|string, string>
     */
    public function getArguments(): array;

    /**
     * @return RouteArray
     */
    public function toArray(): array;
}

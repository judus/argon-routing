<?php

namespace Maduser\Argon\Routing\Contracts;

use Maduser\Argon\Container\Exceptions\ContainerException;
use ReflectionException;

interface RouteCompilerInterface
{
    /**
     * @throws ContainerException
     * @throws ReflectionException
     */
    public function compile(string $method, string $pattern, string|array|callable $handler, array $middlewares = []): void;
}
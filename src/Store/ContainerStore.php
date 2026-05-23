<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Store;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\Support\ReflectionUtils;
use Maduser\Argon\Container\Support\ServiceInvoker;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Exception\RouteHandlerException;
use Maduser\Argon\Routing\Exception\RouterException;
use ReflectionException;

/**
 * @psalm-import-type RouteArray from RouteInterface
 */
final readonly class ContainerStore implements RouteStoreInterface
{
    public function __construct(
        private ArgonContainer $container
    ) {
    }

    /** @inheritdoc */
    #[\Override]
    public function all(string $method): array
    {
        $tag = 'route.' . strtoupper($method);
        /** @var array<string, RouteArray> */
        return $this->container->getTaggedMeta($tag);
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    #[\Override]
    public function add(RouteInterface $route): void
    {
        $handler = $route->getHandler();

        if (is_array($handler)) {
            if (!isset($handler[0]) || !is_string($handler[0])) {
                throw RouterException::forMalformedHandlerDefinition($handler);
            }

            $class = $handler[0];
            $methodName = isset($handler[1]) ? (string) $handler[1] : '__invoke';
        } elseif (is_string($handler)) {
            [$class, $methodName] = $this->parseStringHandler($handler);
        } else {
            throw RouterException::forUnsupportedClosureInContainerStore();
        }

        if (!class_exists($class)) {
            throw RouteHandlerException::forPreparationFailure(
                $route->getPattern(),
                $class,
                $methodName,
                new ReflectionException("Handler class [$class] does not exist.")
            );
        }

        try {
            $args = ReflectionUtils::getMethodParameters($class, $methodName);
        } catch (ReflectionException | ContainerException $e) {
            throw RouteHandlerException::forPreparationFailure(
                $route->getPattern(),
                $class,
                $methodName,
                $e
            );
        }

        $descriptor = $this->container->has($class)
            ? $this->container->getDescriptor($class)
            : $this->container->set($class);

        /**
         * If the container has $class, it also has the descriptor
         * @var ServiceDescriptor $descriptor
         */
        try {
            $descriptor->defineInvocation($methodName, $args);
        } catch (ContainerException $e) {
            throw RouteHandlerException::forPreparationFailure(
                $route->getPattern(),
                $class,
                $methodName,
                $e
            );
        }

        $routeKey = $route->getPattern();
        $routeTag = 'route.' . strtoupper($route->getMethod());



        /** @var array<string, mixed> $meta */
        $meta = $route->toArray();

        $this->container->set($routeKey, ServiceInvoker::class, args: [
            'serviceId' => $class,
            'method' => $methodName,
        ])->tag([$routeTag => $meta]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseStringHandler(string $handler): array
    {
        if (!str_contains($handler, '@')) {
            return [$handler, '__invoke'];
        }

        $parts = explode('@', $handler, 2);
        $class = $parts[0];
        $method = $parts[1] ?? '';

        if ($class === '' || $method === '') {
            throw RouterException::forMalformedHandlerDefinition([$class, $method]);
        }

        return [$class, $method];
    }
}

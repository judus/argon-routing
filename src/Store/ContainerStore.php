<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Store;

use Closure;
use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Container\ServiceDescriptor;
use Maduser\Argon\Container\Support\ReflectionUtils;
use Maduser\Argon\Container\Support\ServiceInvoker;
use Maduser\Argon\Routing\Contracts\RouteStoreInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use ReflectionException;
use RuntimeException;

final readonly class ContainerStore implements RouteStoreInterface
{
    public function __construct(
        private ArgonContainer $container
    ) {
    }

    /** @inheritdoc */
    public function all(string $method): array
    {
        /**
         * @var array<string, array{
         *     method: string,
         *     name?: string,
         *     pattern: string,
         *     compiled?: string,
         *     handler: class-string|array{0: class-string, 1: string}|Closure,
         *     pipelineId?: string,
         *     middlewares?: list<class-string>
         * }>
         */
        return $this->container->getTaggedMeta($method);
    }

    public function get(string $routeKey): array
    {
    }

    /**
     * @throws ReflectionException
     * @throws ContainerException
     */
    public function add(RouteInterface $route): void
    {
        $handler = $route->getHandler();
        $methodName = '__invoke';

        if (is_array($handler)) {
            [$class, $methodName] = $handler;
        } elseif (is_string($handler)) {
            $class = $handler;
        } else {
            throw new RuntimeException('Closure handlers are not supported in container-based routes.');
        }

        /**
         * @var class-string $class
         * @var string $methodName
         */
        $args = ReflectionUtils::getMethodParameters($class, $methodName);

        $descriptor = $this->container->has($class)
            ? $this->container->getDescriptor($class)
            : $this->container->set($class);

        /**
         * If the container has $class, it also has the descriptor
         * @var ServiceDescriptor $descriptor
         */
        $descriptor->defineInvocation($methodName, $args);

        $routeKey = $route->getPattern();
        $routeTag = 'route.' . strtoupper($route->getMethod());



        /** @var array<string, mixed> $meta */
        $meta = $route->toArray();

        $this->container->set($routeKey, ServiceInvoker::class, args: [
            'serviceId' => $class,
            'method' => $methodName,
        ])->tag([$routeTag => $meta]);
    }
}

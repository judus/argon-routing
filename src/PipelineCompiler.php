<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Container\ArgonContainer;
use Maduser\Argon\Container\Exceptions\ContainerException;
use Maduser\Argon\Middleware\MiddlewareStack;
use Maduser\Argon\Middleware\Store\ContainerStore;

final readonly class PipelineCompiler
{
    public function __construct(
        private ArgonContainer $container,
        private ContainerStore $store
    ) {
    }

    /**
     * @throws ContainerException
     */
    public function compile(MiddlewareStack $stack): void
    {

        if (empty($stack->toArray())) {
            return;
        }

        $meta = $this->container->getTaggedMeta('middleware.http');

        $expanded = $this->expandGroupAliases($stack->toArray(), $meta);

        $sortedStack = $this->buildSortedStack($expanded, $meta);

        $this->store->register($sortedStack);
    }

    private function expandGroupAliases(array $middleware, array $taggedMeta): array
    {
        $expanded = [];

        foreach ($middleware as $entry) {
            $entryGroups = array_map('trim', explode(',', $entry));

            foreach ($taggedMeta as $class => $meta) {
                $groups = [];

                if (isset($meta['group'])) {
                    $groups = is_array($meta['group']) ? $meta['group'] : array_map('trim', explode(',', (string) $meta['group']));
                }

                if (array_intersect($entryGroups, $groups)) {
                    $expanded[] = $class;
                }
            }
        }

        return array_unique($expanded);
    }


    /**
     * @param list<class-string> $middleware
     * @param array<string, array<string, mixed>> $meta
     */
    private function buildSortedStack(array $middleware, array $meta): MiddlewareStack
    {
        // Add default priority if missing
        $definitions = array_map(
            fn(string $id) => [
                'id' => $id,
                'priority' => (int)($meta[$id]['priority'] ?? 0),
            ],
            $middleware
        );

        usort($definitions, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return new MiddlewareStack(array_column($definitions, 'id'));
    }
}

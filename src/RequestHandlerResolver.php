<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing;

use Maduser\Argon\Middleware\Contracts\PipelineStoreInterface;
use Maduser\Argon\Routing\Contracts\RequestHandlerResolverInterface;
use Maduser\Argon\Routing\Contracts\RouteInterface;
use Maduser\Argon\Routing\Contracts\RouteMatcherInterface;
use Maduser\Argon\Routing\Middleware\MiddlewareStack;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RequestHandlerResolver implements RequestHandlerResolverInterface
{
    public function __construct(
        private RouteMatcherInterface $matcher,
        private PipelineStoreInterface $pipelines,
        private LoggerInterface $logger,
    ) {
    }

    #[\Override]
    public function resolve(ServerRequestInterface $request): RequestHandlerInterface
    {
        /** @var RouteInterface|null $route */
        $route = $request->getAttribute(RouteInterface::class);

        if (!$route instanceof RouteInterface) {
            $route = $this->matcher->match($request);
        }

        $this->logger->info('Matched route', [
            'name' => $route->getName(),
            'arguments' => $route->getArguments(),
            'pipelineId' => $route->getPipelineId(),
        ]);

        $pipelineId = $route->getPipelineId();
        $stack = new MiddlewareStack($route->getMiddlewares());

        $pipeline = $pipelineId !== null
            ? $this->pipelines->get($pipelineId)
            : $this->pipelines->get($stack);

        $this->logger->info('RequestHandler resolved', [
            'pipeline' => $pipelineId ?? $stack->toArray(),
        ]);

        return new RoutedRequestHandler($pipeline, $route);
    }
}

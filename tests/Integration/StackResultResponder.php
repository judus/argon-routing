<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration;

use JsonException;
use JsonSerializable;
use Maduser\Argon\Support\Contracts\ResultResponderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Stringable;

final readonly class StackResultResponder implements ResultResponderInterface
{
    /**
     * @throws JsonException
     */
    #[Override]
    public function respond(mixed $result, ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        $psr17 = new Psr17Factory();

        if ($result instanceof ResponseInterface) {
            return $result;
        }

        if ($result instanceof JsonSerializable || is_array($result)) {
            return $psr17->createResponse()
                ->withHeader('X-Argon-Result-Type', 'array')
                ->withBody($psr17->createStream(json_encode($result, JSON_THROW_ON_ERROR)));
        }

        if (is_string($result) || $result instanceof Stringable) {
            return $psr17->createResponse()
                ->withHeader('X-Argon-Result-Type', 'string')
                ->withBody($psr17->createStream((string) $result));
        }

        return $psr17->createResponse(500)
            ->withHeader('X-Argon-Result-Type', get_debug_type($result));
    }
}

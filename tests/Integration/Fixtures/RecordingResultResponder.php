<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Fixtures;

use Maduser\Argon\Support\Contracts\ResultResponderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RecordingResultResponder implements ResultResponderInterface
{
    /** @var list<mixed> */
    public array $results = [];

    #[Override]
    public function respond(mixed $result, ServerRequestInterface $request): ResponseInterface
    {
        unset($request);

        $this->results[] = $result;

        $psr17 = new Psr17Factory();

        return $psr17->createResponse()
            ->withHeader('X-Argon-Responder', 'recording')
            ->withBody($psr17->createStream(is_scalar($result) ? (string) $result : get_debug_type($result)));
    }
}

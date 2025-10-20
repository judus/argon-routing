<?php

declare(strict_types=1);

namespace Maduser\Argon\Routing\Tests\Integration\Fixtures;

use Maduser\Argon\Middleware\Contracts\ResultContextInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Records values passed through the result context.
 *
 * @internal Only for test fixtures.
 */
final class RecordingResultContext implements ResultContextInterface
{
    /** @var list<mixed> */
    public array $values = [];

    public function set(mixed $result): ResultContextInterface
    {
        $this->values[] = is_array($result)
            ? implode(',', array_map(fn($key, $value) => $key . '=' . $value, array_keys($result), $result))
            : $result;

        return $this;
    }

    public function get(): mixed
    {
        return $this->values[array_key_last($this->values)] ?? null;
    }

    public function has(): bool
    {
        return $this->values !== [];
    }

    public function is(string $type): bool
    {
        $last = $this->get();
        return $last !== null && $last instanceof $type;
    }

    public function isString(): bool
    {
        return is_string($this->get());
    }

    public function isScalar(): bool
    {
        return is_scalar($this->get());
    }

    public function isClosure(): bool
    {
        return $this->get() instanceof \Closure;
    }

    public function isResponse(): bool
    {
        return $this->get() instanceof ResponseInterface;
    }

    public function isArray(): bool
    {
        return is_array($this->get());
    }

    public function isObject(): bool
    {
        $last = $this->get();
        return is_object($last) && !$this->isResponse();
    }

    public function isCallable(): bool
    {
        return is_callable($this->get());
    }
}

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

    #[\Override]
    public function set(mixed $result): ResultContextInterface
    {
        if (is_array($result)) {
            $parts = [];
            foreach ($result as $key => $value) {
                $parts[] = (string) $key . '=' . (is_scalar($value) ? (string) $value : get_debug_type($value));
            }

            $this->values[] = implode(',', $parts);
            return $this;
        }

        $this->values[] = $result;

        return $this;
    }

    #[\Override]
    public function get(): mixed
    {
        $lastKey = array_key_last($this->values);
        return $lastKey !== null ? $this->values[$lastKey] : null;
    }

    #[\Override]
    public function has(): bool
    {
        return $this->values !== [];
    }

    #[\Override]
    public function is(string $type): bool
    {
        $last = $this->get();
        return $last !== null && $last instanceof $type;
    }

    #[\Override]
    public function isString(): bool
    {
        return is_string($this->get());
    }

    #[\Override]
    public function isScalar(): bool
    {
        return is_scalar($this->get());
    }

    #[\Override]
    public function isClosure(): bool
    {
        return $this->get() instanceof \Closure;
    }

    #[\Override]
    public function isResponse(): bool
    {
        return $this->get() instanceof ResponseInterface;
    }

    #[\Override]
    public function isArray(): bool
    {
        return is_array($this->get());
    }

    #[\Override]
    public function isObject(): bool
    {
        $last = $this->get();
        return is_object($last) && !$this->isResponse();
    }

    #[\Override]
    public function isCallable(): bool
    {
        return is_callable($this->get());
    }
}

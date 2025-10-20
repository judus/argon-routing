<?php

declare(strict_types=1);

namespace Maduser\Argon\Contracts\Http\Server;

if (!interface_exists(ResultContextInterface::class)) {
    /**
     * Temporary test-only polyfill until the real contract is available via composer.
     * @internal remove once maduser/argon-prophecy is accessible in CI.
     */
    interface ResultContextInterface
    {
        public function set(mixed $result): ResultContextInterface;

        public function get(): mixed;

        public function has(): bool;

        public function is(string $type): bool;

        public function isString(): bool;

        public function isScalar(): bool;

        public function isClosure(): bool;

        public function isResponse(): bool;

        public function isArray(): bool;

        public function isObject(): bool;

        public function isCallable(): bool;
    }
}

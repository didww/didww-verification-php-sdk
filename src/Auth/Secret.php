<?php

declare(strict_types=1);

namespace Didww\Verification\Auth;

use Didww\Verification\Exception\ConfigurationException;

/**
 * An application secret: unpadded URL-safe base64, as shown in the DIDWW panel.
 *
 * Validated on construction so a malformed value fails here rather than as a 401 on the first
 * request. Kept out of dumps and serialization.
 *
 * @internal
 */
final class Secret
{
    /**
     * Outside the object's properties, so var_export(), (array) casts and dumpers that ignore
     * __debugInfo() have nothing to print.
     *
     * @var \WeakMap<self, array{string, string}>|null
     */
    private static ?\WeakMap $bytes = null;

    public function __construct(#[\SensitiveParameter] string $secret)
    {
        $body = rtrim($secret, '=');
        if ('' === $body) {
            throw new ConfigurationException('Application secret is empty.');
        }
        if (1 !== preg_match('/\A[A-Za-z0-9_+\/-]+\z/', $body)) {
            throw new ConfigurationException('Application secret is not base64: it contains a character outside the base64 alphabet (a stray space, a newline, or a dash pasted as an en dash).');
        }
        if (1 === \strlen($body) % 4) {
            throw new ConfigurationException('Application secret is not a valid base64 length.');
        }

        $padded = strtr($body, '-_', '+/').str_repeat('=', (4 - \strlen($body) % 4) % 4);
        $key = base64_decode($padded, true);
        if (false === $key) {
            throw new ConfigurationException('Application secret is not base64.');
        }

        self::$bytes ??= new \WeakMap();
        self::$bytes[$this] = [$secret, $key];
    }

    /**
     * The secret exactly as given, for the Basic scheme.
     */
    public function value(): string
    {
        return $this->bytes()[0];
    }

    /**
     * The decoded bytes: the HMAC key.
     */
    public function key(): string
    {
        return $this->bytes()[1];
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['secret' => '[redacted]'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new \LogicException('A secret cannot be serialized.');
    }

    /**
     * @return array{string, string}
     */
    private function bytes(): array
    {
        $bytes = self::$bytes[$this] ?? null;
        if (null === $bytes) {
            throw new \LogicException('This secret was not constructed.');
        }

        return $bytes;
    }
}

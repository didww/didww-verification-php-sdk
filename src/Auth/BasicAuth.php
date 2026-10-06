<?php

declare(strict_types=1);

namespace Didww\Verification\Auth;

/**
 * Sends "Authorization: Basic base64(key:secret)". Server-to-server only.
 */
final class BasicAuth
{
    public readonly string $key;

    private readonly Secret $secret;

    public function __construct(string $key, #[\SensitiveParameter] string $secret)
    {
        $this->key = PublicAuth::checkKey($key);
        $this->secret = new Secret($secret);
    }

    public function authorization(): string
    {
        return 'Basic '.base64_encode($this->key.':'.$this->secret->value());
    }
}

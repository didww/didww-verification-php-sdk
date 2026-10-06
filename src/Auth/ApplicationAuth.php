<?php

declare(strict_types=1);

namespace Didww\Verification\Auth;

use Didww\Verification\Signing\Signer;

/**
 * Signs every request with HMAC-SHA256 and sends "Authorization: Application <key>:<signature>"
 * plus an "x-timestamp" header. The strongest scheme.
 */
final class ApplicationAuth
{
    public readonly string $key;

    private readonly Secret $secret;

    public function __construct(string $key, #[\SensitiveParameter] string $secret)
    {
        $this->key = PublicAuth::checkKey($key);
        $this->secret = new Secret($secret);
    }

    /**
     * The Authorization header value for one request. Send $timestamp as "x-timestamp".
     *
     * @param string      $path        the request path as sent, query excluded
     * @param string      $contentType the Content-Type header as sent, or "" when none is sent
     * @param string|null $body        the exact body bytes, or null when there is no body
     */
    public function authorization(string $method, string $path, string $contentType, ?string $body, int $timestamp): string
    {
        $signature = Signer::sign(
            $this->secret->key(),
            Signer::stringToSign($method, $path, $contentType, $body, $timestamp),
        );

        return 'Application '.$this->key.':'.$signature;
    }
}

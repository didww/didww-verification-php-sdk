<?php

declare(strict_types=1);

namespace Didww\Verification\Signing;

/**
 * HMAC-SHA256 request signing, shared by outbound requests and inbound callbacks.
 *
 * The string to sign is five lines joined by "\n", with no trailing newline:
 * METHOD, CONTENT-MD5, CONTENT-TYPE, "x-timestamp:" TIMESTAMP, PATH.
 */
final class Signer
{
    private function __construct()
    {
    }

    /**
     * @param string      $path        the request path as sent, percent-encoding intact, query excluded
     * @param string      $contentType the Content-Type header as sent, or "" when none is sent
     * @param string|null $body        the exact body bytes, or null when there is no body
     */
    public static function stringToSign(string $method, string $path, string $contentType, ?string $body, int|string $timestamp): string
    {
        return implode("\n", [
            strtoupper($method),
            self::contentMd5($body),
            $contentType,
            'x-timestamp:'.$timestamp,
            $path,
        ]);
    }

    /**
     * Base64 of the MD5 of the body, or "" when the body is absent or ASCII whitespace only.
     *
     * The service checks the raw body bytes for presence, not emptiness: ASCII whitespace counts
     * as no body, while Unicode whitespace such as U+00A0 is hashed like any other content.
     */
    public static function contentMd5(?string $body): string
    {
        if (null === $body || 1 === preg_match('/\A[ \t\n\x0B\f\r]*\z/', $body)) {
            return '';
        }

        return base64_encode(md5($body, true));
    }

    /**
     * @param string $key the decoded secret bytes
     */
    public static function sign(#[\SensitiveParameter] string $key, string $stringToSign): string
    {
        return base64_encode(hash_hmac('sha256', $stringToSign, $key, true));
    }
}

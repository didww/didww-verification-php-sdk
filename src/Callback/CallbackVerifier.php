<?php

declare(strict_types=1);

namespace Didww\Verification\Callback;

use Didww\Verification\Auth\Secret;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Internal\SystemClock;
use Didww\Verification\Signing\Signer;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Checks the signature on an inbound verification callback.
 *
 * Pass the callback URL exactly as registered with DIDWW: the signature covers that URL's path,
 * not the path the request arrives on, which a proxy or a mount prefix can change. A URL with
 * no path signs the empty string, so "https://example.com" and "https://example.com/" differ.
 */
final class CallbackVerifier
{
    private readonly Secret $secret;

    private readonly string $path;

    private readonly ClockInterface $clock;

    public function __construct(
        #[\SensitiveParameter] string $secret,
        string $callbackUrl,
        private readonly int $toleranceSeconds = 300,
        ?ClockInterface $clock = null,
    ) {
        if ($toleranceSeconds < 0) {
            throw new ConfigurationException('Tolerance must not be negative.');
        }
        $parts = parse_url($callbackUrl);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new ConfigurationException('Callback URL must be an absolute URL.');
        }

        $this->secret = new Secret($secret);
        $this->path = $parts['path'] ?? '';
        $this->clock = $clock ?? new SystemClock();
    }

    /**
     * The path this verifier signs: "" for a registered URL without a path.
     */
    public function signedPath(): string
    {
        return $this->path;
    }

    /**
     * Returns null when the callback is authentic, otherwise why it is not.
     *
     * @param string $rawBody the exact bytes received; a re-encoded body will not match
     */
    public function check(string $method, string $contentType, string $rawBody, ?string $timestamp, ?string $authorization): ?RejectionReason
    {
        $signature = self::signature($authorization);
        if (null === $signature) {
            return RejectionReason::MissingSignature;
        }
        if (null === $timestamp || '' === $timestamp) {
            return RejectionReason::MissingTimestamp;
        }
        if (1 !== preg_match('/\A[0-9]+\z/', $timestamp)) {
            return RejectionReason::MalformedTimestamp;
        }
        if (abs($this->clock->now()->getTimestamp() - (int) $timestamp) > $this->toleranceSeconds) {
            return RejectionReason::StaleTimestamp;
        }

        $expected = Signer::sign(
            $this->secret->key(),
            Signer::stringToSign($method, $this->path, $contentType, $rawBody, $timestamp),
        );

        return hash_equals($expected, $signature) ? null : RejectionReason::BadSignature;
    }

    public function isValid(string $method, string $contentType, string $rawBody, ?string $timestamp, ?string $authorization): bool
    {
        return null === $this->check($method, $contentType, $rawBody, $timestamp, $authorization);
    }

    /**
     * check() for a PSR-7 request. A seekable body is rewound before and after reading, so it
     * can have been read already; a consumed non-seekable body cannot be verified.
     */
    public function checkRequest(ServerRequestInterface $request): ?RejectionReason
    {
        $stream = $request->getBody();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $body = $stream->getContents();
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $this->check(
            $request->getMethod(),
            $request->getHeaderLine('Content-Type'),
            $body,
            $request->hasHeader('x-timestamp') ? $request->getHeaderLine('x-timestamp') : null,
            $request->hasHeader('Authorization') ? $request->getHeaderLine('Authorization') : null,
        );
    }

    public function isValidRequest(ServerRequestInterface $request): bool
    {
        return null === $this->checkRequest($request);
    }

    /**
     * The signature from "Application <key>:<signature>"; null for any other form.
     */
    private static function signature(?string $authorization): ?string
    {
        if (null === $authorization || !str_starts_with($authorization, 'Application ')) {
            return null;
        }
        $credentials = substr($authorization, \strlen('Application '));
        $colon = strpos($credentials, ':');
        if (false === $colon || 0 === $colon || $colon === \strlen($credentials) - 1) {
            return null;
        }

        return substr($credentials, $colon + 1);
    }
}

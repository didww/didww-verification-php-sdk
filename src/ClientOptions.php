<?php

declare(strict_types=1);

namespace Didww\Verification;

use Didww\Verification\Exception\ConfigurationException;
use Psr\Clock\ClockInterface;

/**
 * Client configuration. There is deliberately no option for default headers: a header added
 * after signing would change what the signature covers.
 */
final class ClientOptions
{
    public readonly string $baseUrl;

    /** @var callable|null */
    public readonly mixed $handler;

    public readonly RetryPolicy $retry;

    /**
     * @param string|null                       $baseUrl        an origin overriding $environment, without a path
     * @param float                             $timeout        total request timeout, seconds
     * @param float                             $connectTimeout connect timeout, seconds
     * @param string|array<string, string>|null $proxy          as Guzzle's "proxy" request option
     * @param bool|string                       $verify         as Guzzle's "verify" request option
     * @param callable|null                     $handler        a Guzzle handler or HandlerStack; a bare handler is wrapped
     *                                                          with HandlerStack::create(), a HandlerStack is used as given
     * @param ClockInterface|null               $clock          source of the x-timestamp header
     */
    public function __construct(
        public readonly Environment $environment = Environment::Production,
        ?string $baseUrl = null,
        public readonly float $timeout = 30.0,
        public readonly float $connectTimeout = 10.0,
        public readonly string|array|null $proxy = null,
        public readonly bool|string $verify = true,
        ?callable $handler = null,
        ?RetryPolicy $retry = null,
        public readonly ?ClockInterface $clock = null,
    ) {
        $this->baseUrl = null === $baseUrl ? $environment->value : self::origin($baseUrl);
        $this->handler = $handler;
        $this->retry = $retry ?? new RetryPolicy();
    }

    private static function origin(string $url): string
    {
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new ConfigurationException('Base URL must be an absolute URL.');
        }
        if (!\in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new ConfigurationException('Base URL must use http or https.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || !\in_array($parts['path'] ?? '', ['', '/'], true)) {
            throw new ConfigurationException('Base URL must be an origin with no path, query or credentials; the SDK adds the API path itself.');
        }

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}

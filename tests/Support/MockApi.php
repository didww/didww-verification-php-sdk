<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Support;

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\RetryPolicy;
use Didww\Verification\VerificationClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * A client wired to a MockHandler, recording every request the handler receives.
 */
final class MockApi
{
    public const KEY = '7c9e6679-7425-40de-944b-e07fc1f90ae7';
    public const SECRET = '31m-CahIKK3u6hpRgs4-MGRtTm9_znlUWAzMNA_9_2s';
    public const ID = '0199a3c4-5e6f-7a8b-9c0d-1e2f3a4b5c6d';
    public const NOW = 1767225600;

    public readonly VerificationClient $client;

    public readonly FakeClock $clock;

    /** @var list<float> */
    public array $sleeps = [];

    /** @var array<mixed>|\ArrayAccess<int, array<mixed>> */
    private array|\ArrayAccess $history = [];

    /**
     * @param list<mixed> $queue responses or exceptions, in order
     */
    public function __construct(array $queue, PublicAuth|BasicAuth|ApplicationAuth|null $auth = null, int $attempts = 2, int $now = self::NOW)
    {
        $this->clock = new FakeClock($now);
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        $retry = new RetryPolicy(
            attempts: $attempts,
            baseDelay: 1.0,
            sleeper: function (float $seconds): void {
                $this->sleeps[] = $seconds;
                $this->clock->advance(max($seconds, 1.0));
            },
            random: static fn (): float => 1.0,
        );

        $this->client = new VerificationClient(
            $auth ?? new ApplicationAuth(self::KEY, self::SECRET),
            new ClientOptions(handler: $stack, retry: $retry, clock: $this->clock),
        );
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        \assert(\is_array($this->history));
        $requests = [];
        foreach ($this->history as $entry) {
            \assert(\is_array($entry) && $entry['request'] instanceof RequestInterface);
            $requests[] = $entry['request'];
        }

        return $requests;
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        $last = end($requests);
        if (false === $last) {
            throw new \LogicException('no request was sent');
        }

        return $last;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function verification(array $overrides = [], int $status = 200): Response
    {
        $data = array_merge([
            'id' => self::ID,
            'destination' => '15555550100',
            'delivery_method' => 'sms',
            'fee' => '0.0125',
            'status' => 'pending',
            'error_code' => null,
            'error_detail' => null,
            'expires_at' => '2026-01-01T00:05:00Z',
            'sms' => ['template' => 'Your code is {code}', 'language' => 'en-US', 'interception_timeout' => 300, 'code_length' => 6],
        ], $overrides);

        return new Response($status, ['Content-Type' => 'application/json'], json_encode(['data' => $data], \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array{code: string, detail: string}> $errors
     * @param array<string, string>                     $headers
     */
    public static function errors(int $status, array $errors, array $headers = []): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'] + $headers, json_encode(['errors' => $errors], \JSON_THROW_ON_ERROR));
    }
}

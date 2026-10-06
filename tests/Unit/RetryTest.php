<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Exception\NotFoundException;
use Didww\Verification\Exception\ServerException;
use Didww\Verification\Exception\TransportException;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\RetryPolicy;
use Didww\Verification\Tests\Support\MockApi;
use Didww\Verification\VerificationClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(VerificationClient): mixed}>
     */
    public static function writes(): iterable
    {
        yield 'start' => [static fn (VerificationClient $c) => $c->startVerification('+15555550100', DeliveryMethod::Sms)];
        yield 'report' => [static fn (VerificationClient $c) => $c->reportVerification(MockApi::ID, DeliveryMethod::Sms, '123456')];
        yield 'report by number' => [static fn (VerificationClient $c) => $c->reportVerificationByNumber('+15555550100', DeliveryMethod::Sms, '123456')];
        yield 'report raw' => [static fn (VerificationClient $c) => $c->reportVerificationRaw(MockApi::ID, 'future_channel', '123456')];
    }

    /**
     * @param \Closure(VerificationClient): mixed $call
     */
    #[DataProvider('writes')]
    public function testWriteIsSentOnceOn5xx(\Closure $call): void
    {
        $api = new MockApi([new Response(503), MockApi::verification()], attempts: 5);

        try {
            $call($api->client);
            self::fail('expected a ServerException');
        } catch (ServerException) {
            self::assertCount(1, $api->requests());
            self::assertSame([], $api->sleeps);
        }
    }

    /**
     * @param \Closure(VerificationClient): mixed $call
     */
    #[DataProvider('writes')]
    public function testWriteIsSentOnceOnTransportError(\Closure $call): void
    {
        $api = new MockApi([new ConnectException('timed out', new Request('POST', 'https://example.com')), MockApi::verification()], attempts: 5);

        try {
            $call($api->client);
            self::fail('expected a TransportException');
        } catch (TransportException) {
            self::assertCount(1, $api->requests());
        }
    }

    public function testRedirectIsNotFollowed(): void
    {
        $api = new MockApi([new Response(307, ['Location' => 'https://example.com/elsewhere']), MockApi::verification()]);

        try {
            $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
            self::fail('expected an exception');
        } catch (ApiException $e) {
            self::assertSame(307, $e->status);
        }
        self::assertCount(1, $api->requests());
    }

    public function testGetIsRetriedAndReSignedWithAFreshTimestamp(): void
    {
        $api = new MockApi([new Response(502), MockApi::verification()]);

        $v = $api->client->getVerification(MockApi::ID);

        self::assertSame(MockApi::ID, $v->id);
        $requests = $api->requests();
        self::assertCount(2, $requests);
        self::assertSame([1.0], $api->sleeps);
        self::assertNotSame($requests[0]->getHeaderLine('x-timestamp'), $requests[1]->getHeaderLine('x-timestamp'));
        self::assertNotSame($requests[0]->getHeaderLine('Authorization'), $requests[1]->getHeaderLine('Authorization'));
        self::assertSame((string) MockApi::NOW, $requests[0]->getHeaderLine('x-timestamp'));
    }

    public function testGetIsRetriedOnTransportError(): void
    {
        $api = new MockApi([new ConnectException('refused', new Request('GET', 'https://example.com')), MockApi::verification()]);

        $api->client->getVerificationByNumber('+15555550100');

        self::assertCount(2, $api->requests());
    }

    public function testGetGivesUpAfterTheConfiguredAttempts(): void
    {
        $api = new MockApi([new Response(500), new Response(500), new Response(500), MockApi::verification()], attempts: 3);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected a ServerException');
        } catch (ServerException) {
            self::assertCount(3, $api->requests());
            self::assertSame([1.0, 2.0], $api->sleeps);
        }
    }

    public function testGetIsNotRetriedOn4xx(): void
    {
        $api = new MockApi([MockApi::errors(404, [['code' => 'not_found', 'detail' => 'not found']]), MockApi::verification()]);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (NotFoundException) {
            self::assertCount(1, $api->requests());
        }
    }

    public function testBackoffIsExponentialWithJitter(): void
    {
        $policy = new RetryPolicy(baseDelay: 0.2, random: static fn (): float => 0.5);

        self::assertEqualsWithDelta(0.1, $policy->delay(1), 1e-9);
        self::assertEqualsWithDelta(0.2, $policy->delay(2), 1e-9);
        self::assertEqualsWithDelta(0.4, $policy->delay(3), 1e-9);
    }

    public function testDefaults(): void
    {
        $policy = new RetryPolicy();

        self::assertSame(2, $policy->attempts);
        $delay = $policy->delay(1);
        self::assertGreaterThanOrEqual(0.0, $delay);
        self::assertLessThanOrEqual(0.2, $delay);
    }

    public function testRejectsInvalidPolicy(): void
    {
        foreach ([static fn () => new RetryPolicy(attempts: 0), static fn () => new RetryPolicy(baseDelay: -1.0)] as $build) {
            try {
                $build();
                self::fail('expected a ConfigurationException');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
    }
}

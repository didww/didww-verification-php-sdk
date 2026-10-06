<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Auth\ApplicationAuth;
use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\Auth\PublicAuth;
use Didww\Verification\Auth\Secret;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Request\CalloutOptions;
use Didww\Verification\Request\SmsOptions;
use Didww\Verification\Signing\Signer;
use Didww\Verification\Tests\Support\MockApi;
use Didww\Verification\VerificationClient;
use Didww\Verification\Version;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class VerificationClientTest extends TestCase
{
    private const BASE = 'https://verification.didww.com';
    private const ID = MockApi::ID;

    /**
     * @return iterable<string, array{\Closure(VerificationClient): mixed, string, string, ?string}>
     */
    public static function endpoints(): iterable
    {
        yield 'start' => [
            static fn (VerificationClient $c) => $c->startVerification('+15555550100', DeliveryMethod::Sms, new SmsOptions(['en-US'])),
            'POST', '/api/v1/verifications',
            '{"data":{"destination":"+15555550100","delivery_method":"sms","sms":{"languages":["en-US"]}}}',
        ];
        yield 'get' => [
            static fn (VerificationClient $c) => $c->getVerification(self::ID),
            'GET', '/api/v1/verifications/'.self::ID, null,
        ];
        yield 'get by number' => [
            static fn (VerificationClient $c) => $c->getVerificationByNumber('+15555550100'),
            'GET', '/api/v1/verifications/by_number/15555550100', null,
        ];
        yield 'report' => [
            static fn (VerificationClient $c) => $c->reportVerification(self::ID, DeliveryMethod::Sms, '123456'),
            'PATCH', '/api/v1/verifications/'.self::ID,
            '{"data":{"delivery_method":"sms","code":"123456"}}',
        ];
        yield 'report by number' => [
            static fn (VerificationClient $c) => $c->reportVerificationByNumber('+15555550100', DeliveryMethod::Callout, '1234'),
            'PATCH', '/api/v1/verifications/by_number/15555550100',
            '{"data":{"delivery_method":"callout","code":"1234"}}',
        ];
        yield 'report raw' => [
            static fn (VerificationClient $c) => $c->reportVerificationRaw(self::ID, 'future_channel', '123456'),
            'PATCH', '/api/v1/verifications/'.self::ID,
            '{"data":{"delivery_method":"future_channel","code":"123456"}}',
        ];
        yield 'report by number raw' => [
            static fn (VerificationClient $c) => $c->reportVerificationByNumberRaw('+15555550100', 'future_channel', '123456'),
            'PATCH', '/api/v1/verifications/by_number/15555550100',
            '{"data":{"delivery_method":"future_channel","code":"123456"}}',
        ];
    }

    /**
     * @return iterable<string, array{\Closure(VerificationClient): mixed, string, string, ?string, string}>
     */
    public static function endpointsInEveryAuthMode(): iterable
    {
        foreach (self::endpoints() as $name => $endpoint) {
            foreach (['public', 'basic', 'application'] as $mode) {
                yield $name.' / '.$mode => [...$endpoint, $mode];
            }
        }
    }

    /**
     * @param \Closure(VerificationClient): mixed $call
     */
    #[DataProvider('endpointsInEveryAuthMode')]
    public function testEmitsExactRequest(\Closure $call, string $method, string $path, ?string $body, string $mode): void
    {
        $auth = match ($mode) {
            'public' => new PublicAuth(MockApi::KEY),
            'basic' => new BasicAuth(MockApi::KEY, MockApi::SECRET),
            default => new ApplicationAuth(MockApi::KEY, MockApi::SECRET),
        };
        $api = new MockApi([MockApi::verification()], $auth);

        $call($api->client);

        $requests = $api->requests();
        self::assertCount(1, $requests);
        $request = $requests[0];
        self::assertSame($method, $request->getMethod());
        self::assertSame(self::BASE.$path, (string) $request->getUri());
        self::assertSame($body ?? '', (string) $request->getBody());
        self::assertSame('didww-verification-php/'.Version::VERSION, $request->getHeaderLine('User-Agent'));
        self::assertSame(null === $body ? [] : ['application/json'], $request->getHeader('Content-Type'));

        if ('application' === $mode) {
            self::assertSignedFor($request, $method, $path, null === $body ? '' : 'application/json', $body, MockApi::NOW);

            return;
        }
        $expected = 'public' === $mode
            ? 'Application '.MockApi::KEY
            : 'Basic '.base64_encode(MockApi::KEY.':'.MockApi::SECRET);
        self::assertSame($expected, $request->getHeaderLine('Authorization'));
        self::assertFalse($request->hasHeader('x-timestamp'));
    }

    /**
     * @return iterable<string, array{\Closure(VerificationClient): mixed, int}>
     */
    public static function goldenRequests(): iterable
    {
        yield 'start a verification' => [static fn (VerificationClient $c) => $c->startVerification('+15555550100', DeliveryMethod::Sms, new SmsOptions(['en-US'])), 0];
        yield 'get a verification by id' => [static fn (VerificationClient $c) => $c->getVerification(self::ID), 1];
        yield 'get a verification by number' => [static fn (VerificationClient $c) => $c->getVerificationByNumber('+1 555 555 0100'), 2];
        yield 'report a code by id' => [static fn (VerificationClient $c) => $c->reportVerification(self::ID, DeliveryMethod::Sms, '123456'), 3];
        yield 'report a code by number' => [static fn (VerificationClient $c) => $c->reportVerificationByNumber('15555550100', DeliveryMethod::Sms, '123456'), 4];
        yield 'get a verification by an id that needs encoding' => [static fn (VerificationClient $c) => $c->getVerification('not a/uuid'), 7];
    }

    /**
     * @param \Closure(VerificationClient): mixed $call
     */
    #[DataProvider('goldenRequests')]
    public function testSignedRequestMatchesTheGoldenVector(\Closure $call, int $index): void
    {
        $json = file_get_contents(__DIR__.'/../Fixtures/signing_vectors.json');
        self::assertIsString($json);
        /** @var array{vectors: list<array{name: string, secret: string, method: string, path: string, timestamp: int, body: ?string, signature: string}>} $fixture */
        $fixture = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        $vector = $fixture['vectors'][$index];
        self::assertSame($this->dataName(), $vector['name']);
        $api = new MockApi([MockApi::verification()], new ApplicationAuth(MockApi::KEY, $vector['secret']), now: $vector['timestamp']);

        $call($api->client);

        $request = $api->lastRequest();
        self::assertSame($vector['method'], $request->getMethod());
        self::assertSame($vector['path'], $request->getUri()->getPath());
        self::assertSame($vector['body'] ?? '', (string) $request->getBody());
        self::assertSame('Application '.MockApi::KEY.':'.$vector['signature'], $request->getHeaderLine('Authorization'));
        self::assertSame((string) $vector['timestamp'], $request->getHeaderLine('x-timestamp'));
    }

    public function testBodylessSignedGetCarriesNoContentTypeAndSignsAnEmptyLine(): void
    {
        $api = new MockApi([MockApi::verification()]);

        $api->client->getVerification(self::ID);

        $request = $api->lastRequest();
        self::assertFalse($request->hasHeader('Content-Type'));
        self::assertSignedFor($request, 'GET', '/api/v1/verifications/'.self::ID, '', null, MockApi::NOW);
    }

    public function testWriteCarriesExactlyTheSignedContentType(): void
    {
        $api = new MockApi([MockApi::verification(status: 201)]);

        $api->client->startVerification('+15555550100', DeliveryMethod::Callout, callout: new CalloutOptions(['de-DE']));

        $request = $api->lastRequest();
        self::assertSame(['application/json'], $request->getHeader('Content-Type'));
        self::assertSignedFor($request, 'POST', '/api/v1/verifications', 'application/json', (string) $request->getBody(), MockApi::NOW);
    }

    public function testStartSendsOnlyTheBlockMatchingTheDeliveryMethod(): void
    {
        $api = new MockApi([MockApi::verification(status: 201), MockApi::verification(status: 201), MockApi::verification(status: 201)]);

        $api->client->startVerification('+15555550100', DeliveryMethod::Sms, new SmsOptions(appHash: 'FA+9qCX9VSu'), new CalloutOptions(['de-DE']));
        $api->client->startVerification('+15555550100', DeliveryMethod::Callout, new SmsOptions(['pl-PL']), new CalloutOptions(['de-DE']));
        $api->client->startVerification('+15555550100', DeliveryMethod::Sms, new SmsOptions());

        self::assertSame(
            [
                '{"data":{"destination":"+15555550100","delivery_method":"sms","sms":{"app_hash":"FA+9qCX9VSu"}}}',
                '{"data":{"destination":"+15555550100","delivery_method":"callout","callout":{"languages":["de-DE"]}}}',
                '{"data":{"destination":"+15555550100","delivery_method":"sms","sms":{}}}',
            ],
            array_map(static fn (RequestInterface $r): string => (string) $r->getBody(), $api->requests()),
        );
    }

    public function testEmptyIdIsRejectedBeforeSending(): void
    {
        $api = new MockApi([MockApi::verification()]);

        foreach ([
            static fn () => $api->client->getVerification(''),
            static fn () => $api->client->reportVerification('', DeliveryMethod::Sms, '1'),
            static fn () => $api->client->reportVerificationRaw('', 'sms', '1'),
        ] as $call) {
            try {
                $call();
                self::fail('expected a ConfigurationException');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $api->requests());
    }

    public function testIdIsPercentEncodedAndSignedAsSent(): void
    {
        $api = new MockApi([MockApi::verification()]);

        $api->client->getVerification('a/b c');

        $request = $api->lastRequest();
        self::assertSame('/api/v1/verifications/a%2Fb%20c', $request->getUri()->getPath());
        self::assertSignedFor($request, 'GET', '/api/v1/verifications/a%2Fb%20c', '', null, MockApi::NOW);
    }

    public function testByNumberSendsDigitsOnly(): void
    {
        $api = new MockApi([MockApi::verification(), MockApi::verification()]);

        $api->client->getVerificationByNumber('+1 555-555-0100');
        $api->client->reportVerificationByNumber('+1.555.555.0100', DeliveryMethod::Sms, '123456');

        self::assertSame('/api/v1/verifications/by_number/15555550100', $api->requests()[0]->getUri()->getPath());
        self::assertSame('/api/v1/verifications/by_number/15555550100', $api->requests()[1]->getUri()->getPath());
    }

    public function testNumberWithoutDigitsIsRejectedBeforeSending(): void
    {
        $api = new MockApi([MockApi::verification()]);

        foreach ([
            static fn () => $api->client->getVerificationByNumber('+ -'),
            static fn () => $api->client->reportVerificationByNumber('', DeliveryMethod::Sms, '1'),
            static fn () => $api->client->reportVerificationByNumberRaw('abc', 'sms', '1'),
        ] as $call) {
            try {
                $call();
                self::fail('expected a ConfigurationException');
            } catch (ConfigurationException) {
                self::addToAssertionCount(1);
            }
        }
        self::assertSame([], $api->requests());
    }

    private static function assertSignedFor(RequestInterface $request, string $method, string $path, string $contentType, ?string $body, int $timestamp): void
    {
        $signature = Signer::sign(
            (new Secret(MockApi::SECRET))->key(),
            Signer::stringToSign($method, $path, $contentType, $body, $timestamp),
        );

        self::assertSame('Application '.MockApi::KEY.':'.$signature, $request->getHeaderLine('Authorization'));
        self::assertSame((string) $timestamp, $request->getHeaderLine('x-timestamp'));
    }
}

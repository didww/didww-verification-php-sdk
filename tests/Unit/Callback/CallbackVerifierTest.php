<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Callback;

use Didww\Verification\Callback\CallbackVerifier;
use Didww\Verification\Callback\RejectionReason;
use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Tests\Support\FakeClock;
use Didww\Verification\Tests\Support\MockApi;
use GuzzleHttp\Psr7\NoSeekStream;
use GuzzleHttp\Psr7\ServerRequest;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallbackVerifierTest extends TestCase
{
    /**
     * @return array{secret: string, method: string, contentType: string, path: string, timestamp: int, body: string, signature: string}
     */
    private static function vector(string $name): array
    {
        $json = file_get_contents(__DIR__.'/../../Fixtures/signing_vectors.json');
        self::assertIsString($json);
        /** @var array{vectors: list<array{name: string, secret: string, method: string, contentType: string, path: string, timestamp: int, body: string, signature: string}>} $fixture */
        $fixture = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        foreach ($fixture['vectors'] as $vector) {
            if ($vector['name'] === $name) {
                return $vector;
            }
        }
        self::fail('no vector named '.$name);
    }

    /**
     * @return array{secret: string, method: string, contentType: string, path: string, timestamp: int, body: string, signature: string}
     */
    private static function withPath(): array
    {
        return self::vector('callback to a URL with a path');
    }

    private static function verifier(string $url = 'https://example.com/webhooks/verification', int $offset = 0, int $tolerance = 300): CallbackVerifier
    {
        $v = self::withPath();

        return new CallbackVerifier($v['secret'], $url, $tolerance, new FakeClock($v['timestamp'] + $offset));
    }

    private static function authorization(string $signature): string
    {
        return 'Application '.MockApi::KEY.':'.$signature;
    }

    public function testValidCallback(): void
    {
        $v = self::withPath();

        self::assertNull(self::verifier()->check('POST', 'application/json', $v['body'], (string) $v['timestamp'], self::authorization($v['signature'])));
        self::assertTrue(self::verifier()->isValid('POST', 'application/json', $v['body'], (string) $v['timestamp'], self::authorization($v['signature'])));
    }

    public function testBareOriginSignsTheEmptyPath(): void
    {
        $v = self::vector('callback to a bare origin');
        $verifier = new CallbackVerifier($v['secret'], 'https://example.com', clock: new FakeClock($v['timestamp']));

        self::assertSame('', $verifier->signedPath());
        self::assertNull($verifier->check('POST', 'application/json', $v['body'], (string) $v['timestamp'], self::authorization($v['signature'])));

        $withSlash = new CallbackVerifier($v['secret'], 'https://example.com/', clock: new FakeClock($v['timestamp']));
        self::assertSame(RejectionReason::BadSignature, $withSlash->check('POST', 'application/json', $v['body'], (string) $v['timestamp'], self::authorization($v['signature'])));
    }

    public function testQueryIsNotSigned(): void
    {
        $v = self::withPath();
        $verifier = self::verifier('https://example.com/webhooks/verification?tenant=42#frag');

        self::assertSame('/webhooks/verification', $verifier->signedPath());
        self::assertNull($verifier->check('POST', 'application/json', $v['body'], (string) $v['timestamp'], self::authorization($v['signature'])));
    }

    /**
     * @return iterable<string, array{?string, ?string, RejectionReason}>
     */
    public static function rejections(): iterable
    {
        $v = self::withPath();
        $ts = (string) $v['timestamp'];
        $auth = self::authorization($v['signature']);

        yield 'no authorization' => [$ts, null, RejectionReason::MissingSignature];
        yield 'empty authorization' => [$ts, '', RejectionReason::MissingSignature];
        yield 'unsigned scheme' => [$ts, 'Application '.MockApi::KEY, RejectionReason::MissingSignature];
        yield 'empty signature' => [$ts, 'Application '.MockApi::KEY.':', RejectionReason::MissingSignature];
        yield 'basic scheme' => [$ts, 'Basic '.base64_encode('a:b'), RejectionReason::MissingSignature];
        yield 'no timestamp' => [null, $auth, RejectionReason::MissingTimestamp];
        yield 'empty timestamp' => ['', $auth, RejectionReason::MissingTimestamp];
        yield 'letters' => ['abc', $auth, RejectionReason::MalformedTimestamp];
        yield 'negative' => ['-1', $auth, RejectionReason::MalformedTimestamp];
        yield 'decimal' => [$ts.'.0', $auth, RejectionReason::MalformedTimestamp];
        yield 'stale' => [(string) ($v['timestamp'] - 301), $auth, RejectionReason::StaleTimestamp];
        yield 'future' => [(string) ($v['timestamp'] + 301), $auth, RejectionReason::StaleTimestamp];
        yield 'huge' => [str_repeat('9', 40), $auth, RejectionReason::StaleTimestamp];
        yield 'wrong signature' => [$ts, self::authorization(base64_encode(str_repeat("\0", 32))), RejectionReason::BadSignature];
    }

    #[DataProvider('rejections')]
    public function testRejection(?string $timestamp, ?string $authorization, RejectionReason $expected): void
    {
        $v = self::withPath();

        self::assertSame($expected, self::verifier()->check('POST', 'application/json', $v['body'], $timestamp, $authorization));
        self::assertFalse(self::verifier()->isValid('POST', 'application/json', $v['body'], $timestamp, $authorization));
    }

    public function testTamperedBodyOrContentTypeIsABadSignature(): void
    {
        $v = self::withPath();
        $ts = (string) $v['timestamp'];
        $auth = self::authorization($v['signature']);

        self::assertSame(RejectionReason::BadSignature, self::verifier()->check('POST', 'application/json', $v['body'].' ', $ts, $auth));
        self::assertSame(RejectionReason::BadSignature, self::verifier()->check('POST', 'application/json; charset=utf-8', $v['body'], $ts, $auth));
        self::assertSame(RejectionReason::BadSignature, self::verifier('https://example.com/other')->check('POST', 'application/json', $v['body'], $ts, $auth));
    }

    public function testToleranceBoundaryIsInclusive(): void
    {
        $v = self::withPath();
        $auth = self::authorization($v['signature']);
        $ts = (string) $v['timestamp'];

        self::assertNull(self::verifier(offset: 300)->check('POST', 'application/json', $v['body'], $ts, $auth));
        self::assertNull(self::verifier(offset: -300)->check('POST', 'application/json', $v['body'], $ts, $auth));
        self::assertSame(RejectionReason::StaleTimestamp, self::verifier(offset: 301)->check('POST', 'application/json', $v['body'], $ts, $auth));
        self::assertNull(self::verifier(offset: 10, tolerance: 10)->check('POST', 'application/json', $v['body'], $ts, $auth));
        self::assertSame(RejectionReason::StaleTimestamp, self::verifier(offset: 1, tolerance: 0)->check('POST', 'application/json', $v['body'], $ts, $auth));
    }

    public function testPsr7RequestAfterTheBodyWasAlreadyRead(): void
    {
        $v = self::withPath();
        $request = (new ServerRequest('POST', 'https://internal.example.com/mounted/prefix', [
            'Content-Type' => 'application/json',
            'x-timestamp' => (string) $v['timestamp'],
            'Authorization' => self::authorization($v['signature']),
        ], $v['body']));
        $request->getBody()->getContents();

        self::assertNull(self::verifier()->checkRequest($request));
        self::assertTrue(self::verifier()->isValidRequest($request));
        self::assertSame($v['body'], $request->getBody()->getContents());
    }

    public function testPsr7RequestWithoutHeaders(): void
    {
        $v = self::withPath();
        $request = new ServerRequest('POST', 'https://example.com/webhooks/verification', ['Content-Type' => 'application/json'], $v['body']);

        self::assertSame(RejectionReason::MissingSignature, self::verifier()->checkRequest($request));
        self::assertSame(
            RejectionReason::MissingTimestamp,
            self::verifier()->checkRequest($request->withHeader('Authorization', self::authorization($v['signature']))),
        );
    }

    public function testConsumedNonSeekableBodyCannotBeVerified(): void
    {
        $v = self::withPath();
        $stream = new NoSeekStream(Utils::streamFor($v['body']));
        $stream->getContents();
        $request = (new ServerRequest('POST', 'https://example.com/webhooks/verification', [
            'Content-Type' => 'application/json',
            'x-timestamp' => (string) $v['timestamp'],
            'Authorization' => self::authorization($v['signature']),
        ]))->withBody($stream);

        self::assertSame(RejectionReason::BadSignature, self::verifier()->checkRequest($request));
    }

    /**
     * @return iterable<string, array{\Closure(): mixed}>
     */
    public static function invalidConfigurations(): iterable
    {
        $secret = self::withPath()['secret'];

        yield 'relative url' => [static fn () => new CallbackVerifier($secret, '/webhooks')];
        yield 'negative tolerance' => [static fn () => new CallbackVerifier($secret, 'https://example.com', -1)];
        yield 'bad secret' => [static fn () => new CallbackVerifier($secret.' ', 'https://example.com')];
    }

    /**
     * @param \Closure(): mixed $build
     */
    #[DataProvider('invalidConfigurations')]
    public function testRejectsInvalidConfiguration(\Closure $build): void
    {
        $this->expectException(ConfigurationException::class);

        $build();
    }

    public function testSecretIsSensitive(): void
    {
        $parameter = (new \ReflectionMethod(CallbackVerifier::class, '__construct'))->getParameters()[0];

        self::assertNotEmpty($parameter->getAttributes(\SensitiveParameter::class));
    }
}

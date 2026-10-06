<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Auth\BasicAuth;
use Didww\Verification\ClientOptions;
use Didww\Verification\Exception\ApiException;
use Didww\Verification\Exception\BalanceInsufficientException;
use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Exception\NotFoundException;
use Didww\Verification\Exception\RateLimitedException;
use Didww\Verification\Exception\ServerException;
use Didww\Verification\Exception\TransportException;
use Didww\Verification\Exception\UnauthorizedException;
use Didww\Verification\Exception\ValidationException;
use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\ErrorCode;
use Didww\Verification\RetryPolicy;
use Didww\Verification\Tests\Support\MockApi;
use Didww\Verification\VerificationClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ErrorResponsesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, ValidationException::class];
        yield '401' => [401, UnauthorizedException::class];
        yield '402' => [402, BalanceInsufficientException::class];
        yield '404' => [404, NotFoundException::class];
        yield '418' => [418, ApiException::class];
        yield '422' => [422, ValidationException::class];
        yield '429' => [429, RateLimitedException::class];
        yield '500' => [500, ServerException::class];
        yield '503' => [503, ServerException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('statuses')]
    public function testNon2xxMapsToTypedException(int $status, string $class): void
    {
        $api = new MockApi([MockApi::errors($status, [['code' => 'not_found', 'detail' => 'not found']])], attempts: 1);

        try {
            $api->client->reportVerification(MockApi::ID, DeliveryMethod::Sms, '1');
            self::fail('expected an exception');
        } catch (ApiException $e) {
            self::assertSame($class, $e::class);
            self::assertSame($status, $e->status);
            self::assertSame(['not_found'], $e->codes());
        }
    }

    public function testKeepsEveryErrorItem(): void
    {
        $api = new MockApi([MockApi::errors(422, [
            ['code' => 'destination_invalid', 'detail' => 'destination is invalid'],
            ['code' => 'languages_invalid', 'detail' => 'languages are invalid'],
            ['code' => 'some_future_code', 'detail' => 'something new'],
        ])]);

        try {
            $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
            self::fail('expected an exception');
        } catch (ValidationException $e) {
            self::assertCount(3, $e->errors);
            self::assertSame(['destination_invalid', 'languages_invalid', 'some_future_code'], $e->codes());
            self::assertTrue($e->hasCode('languages_invalid'));
            self::assertFalse($e->hasCode('code_invalid'));
            self::assertSame(ErrorCode::DestinationInvalid, $e->errors[0]->codeEnum());
            self::assertNull($e->errors[2]->codeEnum());
            self::assertSame('destination is invalid, languages are invalid, something new', $e->getMessage());
        }
    }

    public function testNonObjectErrorEntriesBecomeDetails(): void
    {
        $api = new MockApi([new Response(422, [], '{"errors":[42,"plain",null]}')]);

        try {
            $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
            self::fail('expected an exception');
        } catch (ValidationException $e) {
            self::assertSame([], $e->codes());
            self::assertSame(['42', 'plain', 'null'], array_map(static fn ($item) => $item->detail, $e->errors));
        }
    }

    public function testDetailOfZeroIsKeptInTheMessage(): void
    {
        $api = new MockApi([MockApi::errors(422, [['code' => 'code_invalid', 'detail' => '0']])]);

        try {
            $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
            self::fail('expected an exception');
        } catch (ValidationException $e) {
            self::assertSame('0', $e->getMessage());
        }
    }

    public function testTransportMessageHidesTheDestinationNumber(): void
    {
        $request = new Request('GET', 'https://example.com/api/v1/verifications/by_number/15555550100');
        $api = new MockApi([new ConnectException('cURL error 7: refused for https://example.com/api/v1/verifications/by_number/15555550100', $request)], attempts: 1);

        try {
            $api->client->getVerificationByNumber('+15555550100');
            self::fail('expected an exception');
        } catch (TransportException $e) {
            self::assertStringNotContainsString('15555550100', $e->getMessage());
            self::assertStringContainsString('by_number/[redacted]', $e->getMessage());
        }
    }

    public function testBodiesAreCutWithoutSplittingACharacter(): void
    {
        $body = 'a'.str_repeat('é', 300);
        $api = new MockApi([new Response(502, [], $body), new Response(200, [], str_repeat('x', 1000))], attempts: 1);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (ServerException $e) {
            self::assertSame('a'.str_repeat('é', 255), $e->body);
        }
        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (DecodingException $e) {
            self::assertSame(str_repeat('x', 512), $e->body);
        }
    }

    public function testRateLimitedExposesRetryAfter(): void
    {
        $api = new MockApi([MockApi::errors(429, [['code' => 'destination_in_cooldown', 'detail' => 'too recently']], ['Retry-After' => '42'])]);

        try {
            $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
            self::fail('expected an exception');
        } catch (RateLimitedException $e) {
            self::assertSame(42, $e->retryAfter);
            self::assertSame(ErrorCode::DestinationInCooldown, $e->errors[0]->codeEnum());
        }
        self::assertCount(1, $api->requests());
    }

    public function testRateLimitedWithoutUsableRetryAfter(): void
    {
        foreach ([[], ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'], ['Retry-After' => '-1'], ['Retry-After' => '12345678901']] as $headers) {
            $api = new MockApi([MockApi::errors(429, [], $headers)]);
            try {
                $api->client->startVerification('+15555550100', DeliveryMethod::Sms);
                self::fail('expected an exception');
            } catch (RateLimitedException $e) {
                self::assertNull($e->retryAfter);
            }
        }
    }

    public function testNonJsonServerErrorKeepsTheRawBody(): void
    {
        $html = '<html><body>Bad Gateway</body></html>';
        $api = new MockApi([new Response(502, ['Content-Type' => 'text/html'], $html)], attempts: 1);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (ServerException $e) {
            self::assertSame(502, $e->status);
            self::assertSame([], $e->errors);
            self::assertSame($html, $e->body);
            self::assertSame('HTTP 502', $e->getMessage());
        }
    }

    public function testConnectExceptionBecomesTransportExceptionWithoutCredentials(): void
    {
        $credentials = base64_encode(MockApi::KEY.':'.MockApi::SECRET);
        $request = new Request('GET', 'https://example.com', ['Authorization' => 'Basic '.$credentials]);
        $api = new MockApi([new ConnectException('Connection refused, sent Basic '.$credentials, $request)], new BasicAuth(MockApi::KEY, MockApi::SECRET), attempts: 1);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (TransportException $e) {
            self::assertNull($e->getPrevious());
            self::assertStringContainsString('Connection refused', $e->getMessage());
            foreach ([$e->getMessage(), (string) $e, print_r($e->getTrace(), true)] as $text) {
                self::assertStringNotContainsString($credentials, $text);
                self::assertStringNotContainsString('GuzzleHttp\\Exception', $text);
            }
        }
    }

    /**
     * @return iterable<string, array{callable(RequestInterface, array<string, mixed>): mixed}>
     */
    public static function failingHandlers(): iterable
    {
        yield 'rejected' => [new MockHandler([new \RuntimeException('boom')])];
        yield 'thrown' => [static function (): never {
            throw new \LogicException('boom');
        }];
    }

    #[DataProvider('failingHandlers')]
    public function testAnyHandlerFailureBecomesTransportException(callable $handler): void
    {
        $client = new VerificationClient(new BasicAuth(MockApi::KEY, MockApi::SECRET), new ClientOptions(handler: HandlerStack::create($handler), retry: new RetryPolicy(attempts: 1)));

        try {
            $client->getVerification(MockApi::ID);
            self::fail('expected an exception');
        } catch (TransportException $e) {
            self::assertSame('boom', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    /**
     * @return iterable<string, array{Response}>
     */
    public static function unreadableSuccesses(): iterable
    {
        yield 'not json' => [new Response(200, [], 'OK')];
        yield 'no data' => [new Response(200, [], '{"errors":[]}')];
        yield 'data is a list' => [new Response(200, [], '{"data":[1]}')];
        yield 'missing id' => [MockApi::verification(['id' => null])];
        yield 'status not a string' => [MockApi::verification(['status' => 3])];
        yield 'sms without code length' => [MockApi::verification(['sms' => ['interception_timeout' => 300]])];
        yield 'bad expires_at' => [MockApi::verification(['expires_at' => 'tomorrow'])];
        yield 'impossible date' => [MockApi::verification(['expires_at' => '2026-02-30T00:00:00Z'])];
        yield 'fee not a decimal' => [MockApi::verification(['fee' => 'free'])];
        yield 'fee with whitespace' => [MockApi::verification(['fee' => ' 1.5'])];
        yield 'offset hours out of range' => [MockApi::verification(['expires_at' => '2026-01-01T00:05:00+24:00'])];
        yield 'offset minutes out of range' => [MockApi::verification(['expires_at' => '2026-01-01T00:05:00+01:60'])];
    }

    #[DataProvider('unreadableSuccesses')]
    public function testUnreadableSuccessIsADecodingExceptionCarryingTheBody(Response $response): void
    {
        $api = new MockApi([$response]);

        try {
            $api->client->getVerification(MockApi::ID);
            self::fail('expected a DecodingException');
        } catch (DecodingException $e) {
            self::assertSame((string) $response->getBody(), $e->body);
        }
    }
}

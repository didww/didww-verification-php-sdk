<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit;

use Didww\Verification\Model\DeliveryMethod;
use Didww\Verification\Model\ErrorCode;
use Didww\Verification\Model\VerificationStatus;
use Didww\Verification\Tests\Support\MockApi;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecodingTest extends TestCase
{
    public function testDecodesAPendingSmsVerification(): void
    {
        $api = new MockApi([MockApi::verification(['sms' => [
            'template' => 'Your code is {code}', 'language' => 'en-US', 'interception_timeout' => 300, 'code_length' => 6, 'app_hash' => 'FA+9qCX9VSu',
        ]], 201)]);

        $v = $api->client->startVerification('+15555550100', DeliveryMethod::Sms);

        self::assertSame(MockApi::ID, $v->id);
        self::assertSame('15555550100', $v->destination);
        self::assertSame(DeliveryMethod::Sms, $v->deliveryMethodEnum());
        self::assertSame('0.0125', $v->fee);
        self::assertTrue($v->isPending());
        self::assertFalse($v->isFinished());
        self::assertFalse($v->isVerified());
        self::assertSame(VerificationStatus::Pending, $v->statusEnum());
        self::assertNull($v->errorCode);
        self::assertNull($v->errorCodeEnum());
        self::assertEquals(new \DateTimeImmutable('2026-01-01T00:05:00Z'), $v->expiresAt);
        self::assertNotNull($v->sms);
        self::assertSame('Your code is {code}', $v->sms->template);
        self::assertSame('en-US', $v->sms->language);
        self::assertSame(300, $v->sms->interceptionTimeout);
        self::assertSame(6, $v->sms->codeLength);
        self::assertSame('FA+9qCX9VSu', $v->sms->appHash);
        self::assertNull($v->callout);
        self::assertSame('0.0125', $v->raw['fee']);
    }

    public function testDecodesACalloutVerification(): void
    {
        $api = new MockApi([MockApi::verification(['delivery_method' => 'callout', 'sms' => null, 'callout' => ['language' => 'de-DE', 'code_length' => 4]])]);

        $v = $api->client->getVerification(MockApi::ID);

        self::assertNull($v->sms);
        self::assertNotNull($v->callout);
        self::assertSame('de-DE', $v->callout->language);
        self::assertSame(4, $v->callout->codeLength);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function outcomes(): iterable
    {
        yield 'failed' => ['failed', 'too_many_attempts'];
        yield 'expired' => ['expired', 'expired'];
        yield 'denied' => ['denied', 'denied_by_callback'];
    }

    #[DataProvider('outcomes')]
    public function testFinishedOutcomesAreData(string $status, string $errorCode): void
    {
        $api = new MockApi([MockApi::verification([
            'status' => $status, 'error_code' => $errorCode, 'error_detail' => 'detail', 'fee' => null, 'expires_at' => null,
            'sms' => ['template' => null, 'language' => null, 'interception_timeout' => 300, 'code_length' => 6],
        ])]);

        $v = $api->client->reportVerification(MockApi::ID, DeliveryMethod::Sms, '000000');

        self::assertSame($status, $v->status);
        self::assertTrue($v->isFinished());
        self::assertFalse($v->isVerified());
        self::assertSame(VerificationStatus::from($status), $v->statusEnum());
        self::assertSame(ErrorCode::from($errorCode), $v->errorCodeEnum());
        self::assertSame('detail', $v->errorDetail);
        self::assertNull($v->fee);
        self::assertNull($v->expiresAt);
        self::assertNotNull($v->sms);
        self::assertNull($v->sms->template);
        self::assertNull($v->sms->language);
        self::assertNull($v->sms->appHash);
    }

    public function testVerified(): void
    {
        $api = new MockApi([MockApi::verification(['status' => 'verified'])]);

        $v = $api->client->reportVerification(MockApi::ID, DeliveryMethod::Sms, '123456');

        self::assertTrue($v->isVerified());
        self::assertTrue($v->isFinished());
    }

    public function testMissingOptionalFieldsDecodeAsNull(): void
    {
        $body = '{"data":{"id":"'.MockApi::ID.'","destination":"15555550100","delivery_method":"sms","status":"pending"}}';
        $api = new MockApi([new Response(200, [], $body)]);

        $v = $api->client->getVerification(MockApi::ID);

        self::assertNull($v->fee);
        self::assertNull($v->errorCode);
        self::assertNull($v->errorDetail);
        self::assertNull($v->expiresAt);
        self::assertNull($v->sms);
        self::assertNull($v->callout);
    }

    public function testUnknownVocabularyDecodes(): void
    {
        $api = new MockApi([MockApi::verification([
            'delivery_method' => 'future_channel', 'status' => 'future_status', 'error_code' => 'future_error', 'sms' => null,
        ])]);

        $v = $api->client->getVerification(MockApi::ID);

        self::assertSame('future_channel', $v->deliveryMethod);
        self::assertNull($v->deliveryMethodEnum());
        self::assertSame('future_status', $v->status);
        self::assertNull($v->statusEnum());
        self::assertTrue($v->isFinished());
        self::assertSame('future_error', $v->errorCode);
        self::assertNull($v->errorCodeEnum());
    }

    public function testUnknownChannelCanBeReportedRaw(): void
    {
        $api = new MockApi([
            MockApi::verification(['delivery_method' => 'future_channel', 'sms' => null]),
            MockApi::verification(['delivery_method' => 'future_channel', 'sms' => null, 'status' => 'verified']),
        ]);

        $v = $api->client->getVerification(MockApi::ID);
        $reported = $api->client->reportVerificationRaw($v->id, $v->deliveryMethod, '123456');

        self::assertTrue($reported->isVerified());
        self::assertSame('{"data":{"delivery_method":"future_channel","code":"123456"}}', (string) $api->lastRequest()->getBody());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fees(): iterable
    {
        yield 'string' => ['"0.0125"', '0.0125'];
        yield 'number keeps trailing zero' => ['0.10', '0.10'];
        yield 'number keeps all digits' => ['0.12345678901234567890', '0.12345678901234567890'];
        yield 'integer' => ['2', '2'];
        yield 'exponent' => ['1.5e-3', '1.5e-3'];
    }

    #[DataProvider('fees')]
    public function testFeeKeepsItsDecimalDigits(string $json, string $expected): void
    {
        $body = '{"data":{"id":"'.MockApi::ID.'","destination":"15555550100","delivery_method":"sms","fee":'.$json.',"status":"pending"}}';
        $api = new MockApi([new Response(200, [], $body)]);

        self::assertSame($expected, $api->client->getVerification(MockApi::ID)->fee);
    }

    public function testFeeDigitsComeFromTheFeeThatWasDecoded(): void
    {
        $body = '{"meta":{"fee":1},"data":{"id":"'.MockApi::ID.'","destination":"15555550100","delivery_method":"sms","fee":0.10,"status":"pending"}}';
        $api = new MockApi([new Response(200, [], $body)]);

        self::assertSame('0.10', $api->client->getVerification(MockApi::ID)->fee);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function timestamps(): iterable
    {
        yield 'zulu' => ['2026-01-01T00:05:00Z', '2026-01-01T00:05:00.000000+00:00'];
        yield 'positive offset' => ['2026-01-01T02:05:00+02:00', '2026-01-01T00:05:00.000000+00:00'];
        yield 'negative offset without colon' => ['2025-12-31T19:05:00-0500', '2026-01-01T00:05:00.000000+00:00'];
        yield 'nanoseconds truncated' => ['2026-01-01T00:05:00.123456789Z', '2026-01-01T00:05:00.123456+00:00'];
        yield 'no offset is utc' => ['2026-01-01T00:05:00', '2026-01-01T00:05:00.000000+00:00'];
    }

    #[DataProvider('timestamps')]
    public function testExpiresAtIsNormalisedToUtc(string $wire, string $expected): void
    {
        $api = new MockApi([MockApi::verification(['expires_at' => $wire])]);

        $expiresAt = $api->client->getVerification(MockApi::ID)->expiresAt;

        self::assertNotNull($expiresAt);
        self::assertSame($expected, $expiresAt->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('UTC', $expiresAt->getTimezone()->getName());
    }
}

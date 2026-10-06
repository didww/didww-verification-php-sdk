<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Unit\Callback;

use Didww\Verification\Callback\CallbackRequest;
use Didww\Verification\Callback\CallbackResponse;
use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Model\DeliveryMethod;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CallbackPayloadTest extends TestCase
{
    public function testParsesTheRequestBody(): void
    {
        $request = CallbackRequest::fromJson('{"event":"verification_request","data":{"id":"0199a3c4-5e6f-7a8b-9c0d-1e2f3a4b5c6d","destination":"15555550100","delivery_method":"sms"}}');

        self::assertSame('verification_request', $request->event);
        self::assertSame('0199a3c4-5e6f-7a8b-9c0d-1e2f3a4b5c6d', $request->id);
        self::assertSame('15555550100', $request->destination);
        self::assertSame('sms', $request->deliveryMethod);
        self::assertSame(DeliveryMethod::Sms, $request->deliveryMethodEnum());
    }

    public function testUnknownDeliveryMethodIsKept(): void
    {
        $request = CallbackRequest::fromJson('{"event":"verification_request","data":{"id":"x","destination":"15555550100","delivery_method":"future_channel"}}');

        self::assertSame('future_channel', $request->deliveryMethod);
        self::assertNull($request->deliveryMethodEnum());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedBodies(): iterable
    {
        yield 'not json' => ['event=verification_request'];
        yield 'no data' => ['{"event":"verification_request"}'];
        yield 'no event' => ['{"data":{"id":"x","destination":"1","delivery_method":"sms"}}'];
        yield 'id not a string' => ['{"event":"e","data":{"id":1,"destination":"1","delivery_method":"sms"}}'];
    }

    #[DataProvider('malformedBodies')]
    public function testRejectsMalformedBody(string $body): void
    {
        $this->expectException(DecodingException::class);

        CallbackRequest::fromJson($body);
    }

    public function testResponses(): void
    {
        self::assertSame(['action' => 'allow'], json_decode(CallbackResponse::allow(), true));
        self::assertSame(['action' => 'deny'], json_decode(CallbackResponse::deny(), true));
    }
}

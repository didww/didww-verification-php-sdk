<?php

declare(strict_types=1);

namespace Didww\Verification\Callback;

use Didww\Verification\Exception\DecodingException;
use Didww\Verification\Model\DeliveryMethod;

/**
 * The body of a callback asking whether a verification may proceed. Verify the request before
 * trusting anything in it.
 */
final class CallbackRequest
{
    public function __construct(
        public readonly string $event,
        public readonly string $id,
        public readonly string $destination,
        public readonly string $deliveryMethod,
    ) {
    }

    /**
     * @throws DecodingException
     */
    public static function fromJson(string $rawBody): self
    {
        try {
            $payload = json_decode($rawBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new DecodingException('Callback body is not JSON: '.$e->getMessage(), $rawBody, $e);
        }
        if (!\is_array($payload) || !isset($payload['data']) || !\is_array($payload['data'])) {
            throw new DecodingException('Callback body has no "data" object.', $rawBody);
        }

        $data = $payload['data'];
        $fields = [
            'event' => $payload['event'] ?? null,
            'id' => $data['id'] ?? null,
            'destination' => $data['destination'] ?? null,
            'delivery_method' => $data['delivery_method'] ?? null,
        ];
        $strings = [];
        foreach ($fields as $field => $value) {
            if (!\is_string($value)) {
                throw new DecodingException(\sprintf('Callback field "%s" is missing or not a string.', $field), $rawBody);
            }
            $strings[$field] = $value;
        }

        return new self($strings['event'], $strings['id'], $strings['destination'], $strings['delivery_method']);
    }

    public function deliveryMethodEnum(): ?DeliveryMethod
    {
        return DeliveryMethod::tryFrom($this->deliveryMethod);
    }
}

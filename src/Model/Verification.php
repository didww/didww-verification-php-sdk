<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * One verification, as every endpoint returns it.
 *
 * deliveryMethod, status and errorCode are raw strings so a value added after this release
 * decodes instead of failing; the ...Enum() helpers return null for such a value. $raw is the
 * decoded "data" object as received, for debugging; its shape is not covered by semantic versioning.
 */
final class Verification
{
    /**
     * @internal
     *
     * @param string|null          $fee decimal, as sent by the API
     * @param array<string, mixed> $raw the decoded "data" object
     */
    public function __construct(
        public readonly string $id,
        public readonly string $destination,
        public readonly string $deliveryMethod,
        public readonly ?string $fee,
        public readonly string $status,
        public readonly ?string $errorCode,
        public readonly ?string $errorDetail,
        public readonly ?\DateTimeImmutable $expiresAt,
        public readonly ?SmsInfo $sms,
        public readonly ?CalloutInfo $callout,
        public readonly array $raw,
    ) {
    }

    public function isPending(): bool
    {
        return VerificationStatus::Pending->value === $this->status;
    }

    public function isVerified(): bool
    {
        return VerificationStatus::Verified->value === $this->status;
    }

    /**
     * True once polling can stop. A status added after this release counts as finished.
     */
    public function isFinished(): bool
    {
        return !$this->isPending();
    }

    public function statusEnum(): ?VerificationStatus
    {
        return VerificationStatus::tryFrom($this->status);
    }

    public function errorCodeEnum(): ?ErrorCode
    {
        return null === $this->errorCode ? null : ErrorCode::tryFrom($this->errorCode);
    }

    public function deliveryMethodEnum(): ?DeliveryMethod
    {
        return DeliveryMethod::tryFrom($this->deliveryMethod);
    }
}

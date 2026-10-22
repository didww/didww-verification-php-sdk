<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * How the SMS is framed for the device's autofill. type is an open string, so a type added
 * after this release still decodes.
 */
final class Autofill
{
    public function __construct(
        public readonly string $type,
        public readonly ?string $value = null,
    ) {
    }

    /**
     * Android SMS Retriever framing for the 11-character app hash.
     */
    public static function appHash(string $value): self
    {
        return new self('app_hash', $value);
    }

    /**
     * Domain-bound framing for the application's configured SMS domain; refused with
     * sms_domain_not_configured when it has none. Read back, value is that domain.
     */
    public static function domain(): self
    {
        return new self('domain');
    }

    /**
     * No marker, regardless of the application default.
     */
    public static function none(): self
    {
        return new self('none');
    }

    /**
     * @internal
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return null === $this->value ? ['type' => $this->type] : ['type' => $this->type, 'value' => $this->value];
    }
}

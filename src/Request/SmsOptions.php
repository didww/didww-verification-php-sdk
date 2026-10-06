<?php

declare(strict_types=1);

namespace Didww\Verification\Request;

/**
 * Options for an SMS start.
 */
final class SmsOptions
{
    /**
     * @param array<string>|null $languages BCP 47 tags in order of preference, e.g. "pl-PL"
     * @param string|null        $appHash   the Android SMS Retriever app hash
     */
    public function __construct(
        public readonly ?array $languages = null,
        public readonly ?string $appHash = null,
    ) {
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            ['languages' => null === $this->languages ? null : array_values($this->languages), 'app_hash' => $this->appHash],
            static fn (mixed $value): bool => null !== $value,
        );
    }
}

<?php

declare(strict_types=1);

namespace Didww\Verification\Request;

/**
 * Options for a callout start.
 */
final class CalloutOptions
{
    /**
     * @param array<string>|null $languages BCP 47 tags in order of preference, e.g. "pl-PL"
     */
    public function __construct(
        public readonly ?array $languages = null,
    ) {
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return null === $this->languages ? [] : ['languages' => array_values($this->languages)];
    }
}

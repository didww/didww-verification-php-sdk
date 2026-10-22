<?php

declare(strict_types=1);

namespace Didww\Verification\Request;

/**
 * Options for a callout start.
 */
final class CalloutOptions
{
    /**
     * @param array<string>|null $languages     BCP 47 tags in order of preference, e.g. "pl-PL"
     * @param string|null        $templateGroup a template group to use instead of the application's default
     */
    public function __construct(
        public readonly ?array $languages = null,
        public readonly ?string $templateGroup = null,
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
            ['languages' => null === $this->languages ? null : array_values($this->languages), 'template_group' => $this->templateGroup],
            static fn (mixed $value): bool => null !== $value,
        );
    }
}

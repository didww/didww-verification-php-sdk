<?php

declare(strict_types=1);

namespace Didww\Verification\Request;

use Didww\Verification\Exception\ConfigurationException;
use Didww\Verification\Model\Autofill;

/**
 * Options for an SMS start.
 */
final class SmsOptions
{
    public readonly ?Autofill $autofill;

    /**
     * @param array<string>|null $languages     BCP 47 tags in order of preference, e.g. "pl-PL"
     * @param string|null        $appHash       deprecated, use $autofill: Autofill::appHash()
     * @param string|null        $templateGroup a template group to use instead of the application's default
     */
    public function __construct(
        public readonly ?array $languages = null,
        /** @deprecated use $autofill */
        public readonly ?string $appHash = null,
        ?Autofill $autofill = null,
        public readonly ?string $templateGroup = null,
    ) {
        if (null !== $appHash) {
            @trigger_error('SmsOptions $appHash is deprecated; use $autofill: Autofill::appHash().', \E_USER_DEPRECATED);
            if (null !== $autofill) {
                throw new ConfigurationException('Pass either autofill or the deprecated appHash, not both.');
            }
            $autofill = Autofill::appHash($appHash);
        }
        $this->autofill = $autofill;
    }

    /**
     * @internal
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            [
                'languages' => null === $this->languages ? null : array_values($this->languages),
                'autofill' => $this->autofill?->toArray(),
                'template_group' => $this->templateGroup,
            ],
            static fn (mixed $value): bool => null !== $value,
        );
    }
}

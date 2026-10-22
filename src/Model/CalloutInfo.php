<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * The "callout" block, present only when the delivery method is callout.
 */
final class CalloutInfo
{
    /**
     * @internal
     *
     * @param string|null $templateGroup          the template group applied, from the request or the application's default
     * @param bool        $templateGroupUsed      true when the announcement came from a template in the group
     * @param string|null $templateFallbackReason why the group's template was not used; an open vocabulary
     */
    public function __construct(
        public readonly ?string $language,
        public readonly int $codeLength,
        public readonly ?string $templateGroup,
        public readonly bool $templateGroupUsed,
        public readonly ?string $templateFallbackReason,
    ) {
    }

    /**
     * The matching case, or null for a reason this release does not know.
     */
    public function templateFallbackReasonEnum(): ?TemplateFallbackReason
    {
        return null === $this->templateFallbackReason ? null : TemplateFallbackReason::tryFrom($this->templateFallbackReason);
    }
}

<?php

declare(strict_types=1);

namespace Didww\Verification\Model;

/**
 * The "sms" block, present only when the delivery method is sms.
 */
final class SmsInfo
{
    /**
     * @internal
     *
     * @param int           $interceptionTimeout    seconds the SMS Retriever stays armed for
     * @param string|null   $appHash                deprecated, use $autofill: its value when its type is app_hash
     * @param string|null   $templateGroup          the template group applied, from the request or the application's default
     * @param bool          $templateGroupUsed      true when the message came from a template in the group
     * @param string|null   $templateFallbackReason why the group's template was not used; an open vocabulary
     * @param Autofill|null $autofill               echoed back only when a hash or domain marker was stored; for domain, value is the domain
     */
    public function __construct(
        public readonly ?string $template,
        public readonly ?string $language,
        public readonly int $interceptionTimeout,
        public readonly int $codeLength,
        /** @deprecated use $autofill */
        public readonly ?string $appHash,
        public readonly ?string $templateGroup,
        public readonly bool $templateGroupUsed,
        public readonly ?string $templateFallbackReason,
        public readonly ?Autofill $autofill,
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

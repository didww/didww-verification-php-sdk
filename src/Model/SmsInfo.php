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
     * @param int         $interceptionTimeout seconds the SMS Retriever stays armed for
     * @param string|null $appHash             echoed back only when a hash was stored
     */
    public function __construct(
        public readonly ?string $template,
        public readonly ?string $language,
        public readonly int $interceptionTimeout,
        public readonly int $codeLength,
        public readonly ?string $appHash,
    ) {
    }
}

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
     */
    public function __construct(
        public readonly ?string $language,
        public readonly int $codeLength,
    ) {
    }
}

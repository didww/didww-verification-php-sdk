<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

use Didww\Verification\Model\ErrorCode;

/**
 * One coded error from an {"errors": [...]} envelope. Switch on the code; show the detail.
 */
final class ErrorItem
{
    /**
     * @internal
     */
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $detail,
    ) {
    }

    public function codeEnum(): ?ErrorCode
    {
        return null === $this->code ? null : ErrorCode::tryFrom($this->code);
    }
}

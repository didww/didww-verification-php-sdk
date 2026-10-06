<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

use Didww\Verification\Internal\BodySnippet;

/**
 * A 2xx response, or a callback body, could not be read as the expected payload.
 */
final class DecodingException extends \RuntimeException implements VerificationException
{
    /**
     * The unreadable body, cut to its first 512 bytes.
     */
    public readonly ?string $body;

    public function __construct(
        string $message,
        ?string $body = null,
        ?\Throwable $previous = null,
    ) {
        $this->body = null === $body ? null : BodySnippet::of($body);
        parent::__construct($message, 0, $previous);
    }
}

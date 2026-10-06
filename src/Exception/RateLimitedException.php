<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

/**
 * 429: a start too soon after the previous one for the same destination. Never retried by the SDK.
 */
final class RateLimitedException extends ApiException
{
    /**
     * @internal
     *
     * @param list<ErrorItem> $errors
     * @param int|null        $retryAfter seconds to wait, from Retry-After; null when absent or unreadable
     */
    public function __construct(
        int $status,
        array $errors,
        string $body,
        public readonly ?int $retryAfter,
    ) {
        parent::__construct($status, $errors, $body);
    }
}

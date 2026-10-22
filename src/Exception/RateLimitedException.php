<?php

declare(strict_types=1);

namespace Didww\Verification\Exception;

/**
 * 429. The error code says which limit was hit:
 *
 * - destination_in_cooldown: a verification for this destination was requested too recently.
 * - rate_limit_per_ip: too many requests from this IP address.
 * - rate_limit_failed_auth: too many failed authentication attempts from this IP address.
 * - rate_limit_per_account: too many requests for this account.
 * - rate_limit_public_per_account: too many public requests for this account.
 * - volume_limit_reached: the application reached its daily or monthly verification limit.
 *   Retry-After can be long (days); surface it instead of retrying.
 *
 * Wait the number of seconds in $retryAfter before trying again.
 *
 * Never retried by the SDK.
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

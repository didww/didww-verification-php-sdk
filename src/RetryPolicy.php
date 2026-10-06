<?php

declare(strict_types=1);

namespace Didww\Verification;

use Didww\Verification\Exception\ConfigurationException;

/**
 * How reads are retried. Starts and reports are never retried: a repeated start supersedes the
 * live verification and charges again, and a repeated report consumes an attempt.
 *
 * A read is retried on a transport failure or a 5xx, with exponential backoff and full jitter.
 */
final class RetryPolicy
{
    /** @var \Closure(float): void */
    private readonly \Closure $sleeper;

    /** @var \Closure(): float */
    private readonly \Closure $random;

    /**
     * @param int                          $attempts  total tries per read, so 2 means one retry
     * @param float                        $baseDelay seconds before the first retry, doubled per retry
     * @param (\Closure(float): void)|null $sleeper   receives the delay in seconds
     * @param (\Closure(): float)|null     $random    jitter factor in [0, 1]
     */
    public function __construct(
        public readonly int $attempts = 2,
        public readonly float $baseDelay = 0.2,
        ?\Closure $sleeper = null,
        ?\Closure $random = null,
    ) {
        if ($attempts < 1) {
            throw new ConfigurationException('Retry attempts must be at least 1.');
        }
        if ($baseDelay < 0) {
            throw new ConfigurationException('Retry base delay must not be negative.');
        }
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
        $this->random = $random ?? static fn (): float => mt_rand() / mt_getrandmax();
    }

    /**
     * @internal
     */
    public function delay(int $attempt): float
    {
        return $this->baseDelay * (2 ** ($attempt - 1)) * ($this->random)();
    }

    /**
     * @internal
     */
    public function wait(int $attempt): void
    {
        ($this->sleeper)($this->delay($attempt));
    }
}

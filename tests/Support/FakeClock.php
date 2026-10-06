<?php

declare(strict_types=1);

namespace Didww\Verification\Tests\Support;

use Psr\Clock\ClockInterface;

final class FakeClock implements ClockInterface
{
    private \DateTimeImmutable $now;

    public function __construct(int $timestamp)
    {
        $this->now = new \DateTimeImmutable('@'.$timestamp);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now = $this->now->modify(\sprintf('+%d seconds', (int) ceil($seconds)));
    }
}

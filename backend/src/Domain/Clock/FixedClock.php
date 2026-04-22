<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Clock;

use DateTimeImmutable;

final class FixedClock implements Clock
{
    public function __construct(private readonly DateTimeImmutable $instant)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}

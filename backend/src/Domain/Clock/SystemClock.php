<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Clock;

use DateTimeImmutable;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now');
    }
}

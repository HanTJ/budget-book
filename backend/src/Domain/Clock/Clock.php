<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}

<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final readonly class MigrationOutcome
{
    public function __construct(
        public int $exitCode,
        public string $output,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->exitCode === 0;
    }
}

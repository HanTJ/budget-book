<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final readonly class PrecheckCheck
{
    public function __construct(
        public string $name,
        public bool $passed,
        public string $message,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final readonly class InstallerInput
{
    public function __construct(
        public string $adminEmail,
        public string $adminPassword,
        public string $adminDisplayName,
    ) {
    }
}

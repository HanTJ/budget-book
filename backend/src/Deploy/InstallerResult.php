<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final readonly class InstallerResult
{
    public function __construct(
        public int $userId,
        public string $adminEmail,
        public int $seededAccountCount,
        public string $migrationOutput,
    ) {
    }
}

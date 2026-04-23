<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

interface Migrator
{
    public function migrate(): MigrationOutcome;
}

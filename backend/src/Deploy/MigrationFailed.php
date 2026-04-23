<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

use RuntimeException;

final class MigrationFailed extends RuntimeException
{
    public static function with(MigrationOutcome $outcome): self
    {
        return new self(sprintf(
            'Phinx migration failed (exit %d): %s',
            $outcome->exitCode,
            $outcome->output,
        ));
    }
}

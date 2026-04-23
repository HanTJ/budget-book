<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

use RuntimeException;

final class AlreadyInstalled extends RuntimeException
{
    public static function at(string $sentinelPath): self
    {
        return new self(sprintf(
            'Installation already completed (sentinel present at %s). Remove the file to reinstall.',
            $sentinelPath,
        ));
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class UserNotFound extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('User not found: %d', $id));
    }
}

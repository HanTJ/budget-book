<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class AccountNotFound extends RuntimeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Account not found: %d', $id));
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class InvalidToken extends RuntimeException
{
    public static function reason(string $why): self
    {
        return new self('Invalid token: ' . $why);
    }
}

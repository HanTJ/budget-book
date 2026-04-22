<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class EmailAlreadyRegistered extends RuntimeException
{
    public static function for(string $email): self
    {
        return new self(sprintf('Email already registered: %s', $email));
    }
}

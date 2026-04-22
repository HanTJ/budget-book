<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class InvalidJournalEntry extends RuntimeException
{
    public static function reason(string $why): self
    {
        return new self('Invalid journal entry: ' . $why);
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

use InvalidArgumentException;

final class Email
{
    private const MAX_LENGTH = 190;

    public readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function of(string $raw): self
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Email must not be blank.');
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Email exceeds maximum length.');
        }

        $normalised = strtolower($trimmed);
        if (filter_var($normalised, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(sprintf('Invalid email: %s', $raw));
        }

        return new self($normalised);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}

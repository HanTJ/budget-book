<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

use InvalidArgumentException;

final class HashedPassword
{
    private const MIN_PLAIN_LENGTH = 8;
    private const BCRYPT_COST = 12;

    public readonly string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    public static function fromPlainText(string $plain): self
    {
        if (strlen($plain) < self::MIN_PLAIN_LENGTH) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $hash = password_hash($plain, PASSWORD_BCRYPT, ['cost' => self::BCRYPT_COST]);

        return new self($hash);
    }

    public static function fromHash(string $hash): self
    {
        $info = password_get_info($hash);
        if (($info['algo'] ?? null) !== PASSWORD_BCRYPT) {
            throw new InvalidArgumentException('Password hash is not a valid bcrypt hash.');
        }

        return new self($hash);
    }

    public function verify(string $plain): bool
    {
        return password_verify($plain, $this->value);
    }
}

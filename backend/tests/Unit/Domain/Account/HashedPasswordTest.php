<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Account;

use BudgetBook\Domain\Account\HashedPassword;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HashedPassword::class)]
final class HashedPasswordTest extends TestCase
{
    public function test_hashes_plaintext_with_bcrypt(): void
    {
        $hashed = HashedPassword::fromPlainText('correct-horse-battery');

        self::assertStringStartsWith('$2y$', $hashed->value);
        self::assertTrue($hashed->verify('correct-horse-battery'));
        self::assertFalse($hashed->verify('wrong-password'));
    }

    public function test_rejects_short_passwords(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HashedPassword::fromPlainText('short');
    }

    public function test_restores_from_existing_hash(): void
    {
        $original = HashedPassword::fromPlainText('correct-horse-battery');
        $restored = HashedPassword::fromHash($original->value);

        self::assertTrue($restored->verify('correct-horse-battery'));
    }

    public function test_rejects_invalid_hash_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        HashedPassword::fromHash('not-a-real-hash');
    }
}

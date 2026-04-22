<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Account;

use BudgetBook\Domain\Account\Email;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Email::class)]
final class EmailTest extends TestCase
{
    public function test_accepts_valid_email(): void
    {
        $email = Email::of('user@example.com');

        self::assertSame('user@example.com', $email->value);
    }

    public function test_normalises_to_lowercase(): void
    {
        $email = Email::of('User@Example.COM');

        self::assertSame('user@example.com', $email->value);
    }

    public function test_rejects_whitespace_only_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::of('   ');
    }

    #[DataProvider('invalidEmails')]
    public function test_rejects_malformed_email(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        Email::of($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidEmails(): iterable
    {
        yield 'no-at' => ['userexample.com'];
        yield 'no-domain' => ['user@'];
        yield 'no-local' => ['@example.com'];
        yield 'spaces' => ['user @example.com'];
    }

    public function test_equality_is_value_based(): void
    {
        self::assertTrue(Email::of('a@b.com')->equals(Email::of('A@B.COM')));
        self::assertFalse(Email::of('a@b.com')->equals(Email::of('c@b.com')));
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Domain\Ledger;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\PaymentMethod;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JournalEntry::class)]
#[CoversClass(JournalEntryLine::class)]
final class JournalEntryTest extends TestCase
{
    public function test_records_balanced_two_line_entry(): void
    {
        $entry = JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: '장보기',
            merchant: '이마트',
            paymentMethod: PaymentMethod::CARD,
            lines: [
                JournalEntryLine::debit(accountId: 10, amount: BigDecimal::of('10000')),
                JournalEntryLine::credit(accountId: 20, amount: BigDecimal::of('10000')),
            ],
        );

        self::assertSame(1, $entry->userId);
        self::assertCount(2, $entry->lines);
        self::assertTrue($entry->totalDebit()->isEqualTo(BigDecimal::of('10000')));
        self::assertTrue($entry->totalCredit()->isEqualTo(BigDecimal::of('10000')));
    }

    public function test_rejects_unbalanced_entry(): void
    {
        $this->expectException(DomainException::class);
        JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: null,
            merchant: null,
            paymentMethod: null,
            lines: [
                JournalEntryLine::debit(10, BigDecimal::of('10000')),
                JournalEntryLine::credit(20, BigDecimal::of('9999')),
            ],
        );
    }

    public function test_rejects_single_line(): void
    {
        $this->expectException(DomainException::class);
        JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: null,
            merchant: null,
            paymentMethod: null,
            lines: [JournalEntryLine::debit(10, BigDecimal::of('10000'))],
        );
    }

    public function test_rejects_zero_amount_line(): void
    {
        $this->expectException(DomainException::class);
        JournalEntryLine::debit(10, BigDecimal::zero());
    }

    public function test_rejects_negative_amount_line(): void
    {
        $this->expectException(DomainException::class);
        JournalEntryLine::debit(10, BigDecimal::of('-1'));
    }

    public function test_allows_multi_leg_balanced_entry(): void
    {
        $entry = JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: '식비 + 배달료 분할',
            merchant: '배달앱',
            paymentMethod: PaymentMethod::CARD,
            lines: [
                JournalEntryLine::debit(10, BigDecimal::of('8000')),  // 식비
                JournalEntryLine::debit(11, BigDecimal::of('2000')),  // 배달료
                JournalEntryLine::credit(20, BigDecimal::of('10000')), // 카드
            ],
        );

        self::assertCount(3, $entry->lines);
        self::assertTrue($entry->totalDebit()->isEqualTo(BigDecimal::of('10000')));
    }

    public function test_rejects_entry_without_lines(): void
    {
        $this->expectException(DomainException::class);
        JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: null,
            merchant: null,
            paymentMethod: null,
            lines: [],
        );
    }

    public function test_line_no_is_assigned_by_insertion_order(): void
    {
        $entry = JournalEntry::record(
            userId: 1,
            occurredOn: new DateTimeImmutable('2026-04-22'),
            memo: null,
            merchant: null,
            paymentMethod: null,
            lines: [
                JournalEntryLine::debit(10, BigDecimal::of('5000')),
                JournalEntryLine::debit(11, BigDecimal::of('3000')),
                JournalEntryLine::credit(20, BigDecimal::of('8000')),
            ],
        );

        self::assertSame(0, $entry->lines[0]->lineNo);
        self::assertSame(1, $entry->lines[1]->lineNo);
        self::assertSame(2, $entry->lines[2]->lineNo);
    }
}

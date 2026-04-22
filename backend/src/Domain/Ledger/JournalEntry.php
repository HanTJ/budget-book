<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

use Brick\Math\BigDecimal;
use DateTimeImmutable;
use DomainException;

final class JournalEntry
{
    /**
     * @param list<JournalEntryLine> $lines
     */
    private function __construct(
        private ?int $id,
        public readonly int $userId,
        public readonly DateTimeImmutable $occurredOn,
        public readonly ?string $memo,
        public readonly ?string $merchant,
        public readonly ?PaymentMethod $paymentMethod,
        public readonly string $source,
        public readonly array $lines,
    ) {
    }

    /**
     * @param list<JournalEntryLine> $lines
     */
    public static function record(
        int $userId,
        DateTimeImmutable $occurredOn,
        ?string $memo,
        ?string $merchant,
        ?PaymentMethod $paymentMethod,
        array $lines,
        string $source = 'USER',
    ): self {
        if (count($lines) < 2) {
            throw new DomainException('Journal entry must have at least two lines.');
        }

        $assigned = [];
        $totalDebit = BigDecimal::zero();
        $totalCredit = BigDecimal::zero();
        foreach ($lines as $index => $line) {
            $line->lineNo = $index;
            $assigned[] = $line;
            $totalDebit = $totalDebit->plus($line->debit);
            $totalCredit = $totalCredit->plus($line->credit);
        }

        if (!$totalDebit->isEqualTo($totalCredit)) {
            throw new DomainException(sprintf(
                'Journal entry is unbalanced: debit=%s credit=%s',
                (string) $totalDebit,
                (string) $totalCredit,
            ));
        }

        if ($totalDebit->isEqualTo(BigDecimal::zero())) {
            throw new DomainException('Journal entry total must be greater than zero.');
        }

        return new self(
            id: null,
            userId: $userId,
            occurredOn: $occurredOn,
            memo: $memo,
            merchant: $merchant,
            paymentMethod: $paymentMethod,
            source: $source,
            lines: $assigned,
        );
    }

    /**
     * @param list<JournalEntryLine> $lines
     */
    public static function hydrate(
        int $id,
        int $userId,
        DateTimeImmutable $occurredOn,
        ?string $memo,
        ?string $merchant,
        ?PaymentMethod $paymentMethod,
        string $source,
        array $lines,
    ): self {
        return new self($id, $userId, $occurredOn, $memo, $merchant, $paymentMethod, $source, $lines);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new DomainException('JournalEntry id already assigned.');
        }
        $this->id = $id;
    }

    public function totalDebit(): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->plus($line->debit);
        }
        return $sum;
    }

    public function totalCredit(): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($this->lines as $line) {
            $sum = $sum->plus($line->credit);
        }
        return $sum;
    }
}

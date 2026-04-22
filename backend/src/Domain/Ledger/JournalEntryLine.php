<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

use Brick\Math\BigDecimal;
use DomainException;

final class JournalEntryLine
{
    public int $lineNo = 0;

    private function __construct(
        public readonly int $accountId,
        public readonly BigDecimal $debit,
        public readonly BigDecimal $credit,
    ) {
        $zero = BigDecimal::zero();
        if ($debit->isNegative() || $credit->isNegative()) {
            throw new DomainException('Line amount must not be negative.');
        }
        $debitPositive = $debit->isGreaterThan($zero);
        $creditPositive = $credit->isGreaterThan($zero);
        if ($debitPositive === $creditPositive) {
            throw new DomainException('Line must have exactly one of debit or credit positive.');
        }
    }

    public static function debit(int $accountId, BigDecimal $amount): self
    {
        return new self($accountId, $amount, BigDecimal::zero());
    }

    public static function credit(int $accountId, BigDecimal $amount): self
    {
        return new self($accountId, BigDecimal::zero(), $amount);
    }

    public function amount(): BigDecimal
    {
        return $this->debit->isGreaterThan(BigDecimal::zero()) ? $this->debit : $this->credit;
    }

    public function isDebit(): bool
    {
        return $this->debit->isGreaterThan(BigDecimal::zero());
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use Brick\Math\BigDecimal;
use DateTimeImmutable;

final class BalanceSheet
{
    /**
     * @param list<BalanceSheetLine> $assets
     * @param list<BalanceSheetLine> $liabilities
     * @param list<BalanceSheetLine> $equity
     */
    public function __construct(
        public readonly DateTimeImmutable $asOf,
        public readonly array $assets,
        public readonly array $liabilities,
        public readonly array $equity,
        private readonly BigDecimal $netIncomeValue,
    ) {
    }

    public function netIncome(): BigDecimal
    {
        return $this->netIncomeValue;
    }

    public function totalAssets(): BigDecimal
    {
        return $this->sum($this->assets);
    }

    public function totalLiabilities(): BigDecimal
    {
        return $this->sum($this->liabilities);
    }

    public function totalEquity(): BigDecimal
    {
        return $this->sum($this->equity)->plus($this->netIncomeValue);
    }

    public function isBalanced(): bool
    {
        return $this->totalAssets()->isEqualTo($this->totalLiabilities()->plus($this->totalEquity()));
    }

    public function assetLineByAccountId(int $accountId): ?BalanceSheetLine
    {
        return $this->findById($this->assets, $accountId);
    }

    public function liabilityLineByAccountId(int $accountId): ?BalanceSheetLine
    {
        return $this->findById($this->liabilities, $accountId);
    }

    public function equityLineByAccountId(int $accountId): ?BalanceSheetLine
    {
        return $this->findById($this->equity, $accountId);
    }

    /**
     * @param list<BalanceSheetLine> $lines
     */
    private function findById(array $lines, int $accountId): ?BalanceSheetLine
    {
        foreach ($lines as $line) {
            if ($line->accountId === $accountId) {
                return $line;
            }
        }
        return null;
    }

    /**
     * @param list<BalanceSheetLine> $lines
     */
    private function sum(array $lines): BigDecimal
    {
        $sum = BigDecimal::zero();
        foreach ($lines as $line) {
            $sum = $sum->plus($line->balance);
        }
        return $sum;
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

enum AccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case INCOME = 'INCOME';
    case EXPENSE = 'EXPENSE';

    public function defaultNormalBalance(): NormalBalance
    {
        return match ($this) {
            self::ASSET, self::EXPENSE => NormalBalance::DEBIT,
            self::LIABILITY, self::EQUITY, self::INCOME => NormalBalance::CREDIT,
        };
    }

    public function requiresCashFlowSection(): bool
    {
        return $this === self::INCOME || $this === self::EXPENSE;
    }
}

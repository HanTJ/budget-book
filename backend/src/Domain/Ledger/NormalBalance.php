<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

enum NormalBalance: string
{
    case DEBIT = 'DEBIT';
    case CREDIT = 'CREDIT';
}

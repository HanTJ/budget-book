<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

enum PaymentMethod: string
{
    case CASH = 'CASH';
    case CARD = 'CARD';
    case TRANSFER = 'TRANSFER';
}

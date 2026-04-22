<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

enum CashFlowSection: string
{
    case OPERATING = 'OPERATING';
    case INVESTING = 'INVESTING';
    case FINANCING = 'FINANCING';
    case NONE = 'NONE';
}

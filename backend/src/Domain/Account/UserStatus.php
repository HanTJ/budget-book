<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

enum UserStatus: string
{
    case PENDING = 'PENDING';
    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
}

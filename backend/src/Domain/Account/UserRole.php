<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

enum UserRole: string
{
    case USER = 'USER';
    case ADMIN = 'ADMIN';
}

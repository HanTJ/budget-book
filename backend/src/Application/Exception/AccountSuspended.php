<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class AccountSuspended extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Account has been suspended.');
    }
}

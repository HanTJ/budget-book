<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class AccountPending extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Account is awaiting administrator approval.');
    }
}

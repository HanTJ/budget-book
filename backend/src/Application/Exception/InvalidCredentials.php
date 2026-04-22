<?php

declare(strict_types=1);

namespace BudgetBook\Application\Exception;

use RuntimeException;

final class InvalidCredentials extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Email or password is incorrect.');
    }
}

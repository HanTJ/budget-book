<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Domain\Account\UserStatus;

final class ListUsersInput
{
    public function __construct(public readonly ?UserStatus $status)
    {
    }
}

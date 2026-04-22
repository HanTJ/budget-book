<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;

final class UpdateUserInput
{
    public function __construct(
        public readonly int $userId,
        public readonly ?UserStatus $status,
        public readonly ?UserRole $role,
    ) {
    }
}

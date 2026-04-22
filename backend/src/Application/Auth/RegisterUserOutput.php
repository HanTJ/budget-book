<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

use BudgetBook\Domain\Account\UserStatus;

final class RegisterUserOutput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
        public readonly UserStatus $status,
    ) {
    }
}

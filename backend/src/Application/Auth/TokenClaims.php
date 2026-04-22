<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

use BudgetBook\Domain\Account\UserRole;

final class TokenClaims
{
    public function __construct(
        public readonly int $userId,
        public readonly UserRole $role,
        public readonly string $type,
    ) {
    }
}

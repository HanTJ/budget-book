<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

use BudgetBook\Domain\Account\UserRole;

interface TokenService
{
    public function issue(int $userId, UserRole $role): TokenPair;

    public function verifyAccess(string $token): TokenClaims;
}

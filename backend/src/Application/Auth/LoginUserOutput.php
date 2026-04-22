<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

final class LoginUserOutput
{
    public function __construct(
        public readonly int $userId,
        public readonly string $accessToken,
        public readonly string $refreshToken,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

final class LoginUserInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $plainPassword,
    ) {
    }
}

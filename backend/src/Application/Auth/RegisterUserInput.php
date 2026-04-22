<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

final class RegisterUserInput
{
    public function __construct(
        public readonly string $email,
        public readonly string $plainPassword,
        public readonly string $displayName,
    ) {
    }
}

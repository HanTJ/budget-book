<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

interface UserRepository
{
    public function findById(int $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function emailExists(Email $email): bool;

    public function save(User $user): void;
}

<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

interface UserRepository
{
    public function findById(int $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function emailExists(Email $email): bool;

    public function save(User $user): void;

    /**
     * @return list<User>
     */
    public function listAll(?UserStatus $status): array;

    public function softDelete(int $id): void;
}

<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;

final class UpdateUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly SeedDefaultAccounts $seed,
    ) {
    }

    public function handle(UpdateUserInput $input): User
    {
        $user = $this->users->findById($input->userId);
        if ($user === null) {
            throw UserNotFound::forId($input->userId);
        }

        $statusTransitionToActive = $input->status !== null
            && $input->status === UserStatus::ACTIVE
            && $user->status !== UserStatus::ACTIVE;

        if ($input->status !== null) {
            match ($input->status) {
                UserStatus::ACTIVE => $user->activate(),
                UserStatus::SUSPENDED => $user->suspend(),
                UserStatus::PENDING => null, // admins cannot push back to PENDING
            };
        }

        if ($input->role === UserRole::ADMIN) {
            $user->promoteToAdmin();
        }

        $this->users->save($user);

        if ($statusTransitionToActive) {
            $this->seed->seed($input->userId);
        }

        return $user;
    }
}

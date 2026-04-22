<?php

declare(strict_types=1);

namespace BudgetBook\Application\Admin;

use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;

final class ListUsers
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @return list<User>
     */
    public function handle(ListUsersInput $input): array
    {
        return $this->users->listAll($input->status);
    }
}

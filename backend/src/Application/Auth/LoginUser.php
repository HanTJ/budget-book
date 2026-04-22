<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

use BudgetBook\Application\Exception\AccountPending;
use BudgetBook\Application\Exception\AccountSuspended;
use BudgetBook\Application\Exception\InvalidCredentials;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Account\UserStatus;
use DomainException;

final class LoginUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly TokenService $tokens,
    ) {
    }

    public function handle(LoginUserInput $input): LoginUserOutput
    {
        $user = $this->users->findByEmail(Email::of($input->email));
        if ($user === null) {
            throw new InvalidCredentials();
        }

        if (!$user->password->verify($input->plainPassword)) {
            throw new InvalidCredentials();
        }

        match ($user->status) {
            UserStatus::PENDING => throw new AccountPending(),
            UserStatus::SUSPENDED => throw new AccountSuspended(),
            UserStatus::ACTIVE => null,
        };

        $id = $user->id();
        if ($id === null) {
            throw new DomainException('Persisted user must have an id.');
        }

        $pair = $this->tokens->issue($id, $user->role);

        return new LoginUserOutput(
            userId: $id,
            accessToken: $pair->accessToken,
            refreshToken: $pair->refreshToken,
        );
    }
}

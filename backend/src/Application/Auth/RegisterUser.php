<?php

declare(strict_types=1);

namespace BudgetBook\Application\Auth;

use BudgetBook\Application\Exception\EmailAlreadyRegistered;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Clock\Clock;
use DomainException;

final class RegisterUser
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Clock $clock,
    ) {
    }

    public function handle(RegisterUserInput $input): RegisterUserOutput
    {
        $email = Email::of($input->email);

        if ($this->users->emailExists($email)) {
            throw EmailAlreadyRegistered::for($email->value);
        }

        $user = User::register(
            email: $email,
            password: HashedPassword::fromPlainText($input->plainPassword),
            displayName: $input->displayName,
            clock: $this->clock,
        );

        $this->users->save($user);

        $id = $user->id();
        if ($id === null) {
            throw new DomainException('User id was not assigned after persistence.');
        }

        return new RegisterUserOutput(
            userId: $id,
            email: $email->value,
            status: $user->status,
        );
    }
}

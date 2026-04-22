<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Account;

use BudgetBook\Domain\Clock\Clock;
use DateTimeImmutable;
use DomainException;

final class User
{
    private function __construct(
        private ?int $id,
        public readonly Email $email,
        public HashedPassword $password,
        public string $displayName,
        public UserRole $role,
        public UserStatus $status,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public static function register(
        Email $email,
        HashedPassword $password,
        string $displayName,
        Clock $clock,
    ): self {
        $name = trim($displayName);
        if ($name === '') {
            throw new DomainException('Display name must not be blank.');
        }

        return new self(
            id: null,
            email: $email,
            password: $password,
            displayName: $name,
            role: UserRole::USER,
            status: UserStatus::PENDING,
            createdAt: $clock->now(),
        );
    }

    public static function hydrate(
        int $id,
        Email $email,
        HashedPassword $password,
        string $displayName,
        UserRole $role,
        UserStatus $status,
        DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $email, $password, $displayName, $role, $status, $createdAt);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new DomainException('User id is already assigned.');
        }
        $this->id = $id;
    }

    public function activate(): void
    {
        if ($this->status === UserStatus::SUSPENDED) {
            throw new DomainException('Suspended user cannot be activated directly.');
        }
        $this->status = UserStatus::ACTIVE;
    }

    public function suspend(): void
    {
        if ($this->status === UserStatus::SUSPENDED) {
            throw new DomainException('User is already suspended.');
        }
        $this->status = UserStatus::SUSPENDED;
    }

    public function promoteToAdmin(): void
    {
        $this->role = UserRole::ADMIN;
    }
}

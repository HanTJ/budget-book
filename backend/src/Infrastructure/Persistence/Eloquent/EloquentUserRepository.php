<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Persistence\Eloquent;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

final class EloquentUserRepository implements UserRepository
{
    private const TABLE = 'users';

    public function findById(int $id): ?User
    {
        /** @var object|null $row */
        $row = $this->table()
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        return $this->hydrate($row);
    }

    public function findByEmail(Email $email): ?User
    {
        /** @var object|null $row */
        $row = $this->table()
            ->whereNull('deleted_at')
            ->where('email', $email->value)
            ->first();

        return $this->hydrate($row);
    }

    public function emailExists(Email $email): bool
    {
        return $this->table()
            ->whereNull('deleted_at')
            ->where('email', $email->value)
            ->exists();
    }

    public function listAll(?\BudgetBook\Domain\Account\UserStatus $status): array
    {
        $query = $this->table()->whereNull('deleted_at')->orderBy('id');
        if ($status !== null) {
            $query->where('status', $status->value);
        }
        $rows = $query->get();

        $users = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrate($row);
            if ($hydrated !== null) {
                $users[] = $hydrated;
            }
        }
        return $users;
    }

    public function softDelete(int $id): void
    {
        $this->table()
            ->where('id', $id)
            ->update(['deleted_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    }

    public function save(User $user): void
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $payload = [
            'email' => $user->email->value,
            'password_hash' => $user->password->value,
            'display_name' => $user->displayName,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'updated_at' => $now,
        ];

        if ($user->id() === null) {
            $payload['created_at'] = $user->createdAt->format('Y-m-d H:i:s');
            $id = (int) $this->table()->insertGetId($payload);
            $user->assignId($id);
            return;
        }

        $this->table()->where('id', $user->id())->update($payload);
    }

    private function table(): Builder
    {
        return Capsule::connection()->table(self::TABLE);
    }

    private function hydrate(?object $row): ?User
    {
        if ($row === null) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return User::hydrate(
            id: (int) ($data['id'] ?? 0),
            email: Email::of((string) ($data['email'] ?? '')),
            password: HashedPassword::fromHash((string) ($data['password_hash'] ?? '')),
            displayName: (string) ($data['display_name'] ?? ''),
            role: UserRole::from((string) ($data['role'] ?? UserRole::USER->value)),
            status: UserStatus::from((string) ($data['status'] ?? UserStatus::PENDING->value)),
            createdAt: new DateTimeImmutable((string) ($data['created_at'] ?? 'now')),
        );
    }
}

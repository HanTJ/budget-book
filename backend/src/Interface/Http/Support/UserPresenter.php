<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use BudgetBook\Domain\Account\User;

final class UserPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(User $user): array
    {
        return [
            'id' => $user->id(),
            'email' => $user->email->value,
            'display_name' => $user->displayName,
            'role' => $user->role->value,
            'status' => $user->status->value,
            'created_at' => $user->createdAt->format(DATE_ATOM),
        ];
    }
}

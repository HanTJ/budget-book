<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Security;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Application\Auth\TokenPair;
use BudgetBook\Application\Auth\TokenService;
use BudgetBook\Application\Exception\InvalidToken;
use BudgetBook\Domain\Account\UserRole;

/**
 * Deterministic non-crypto token service for unit tests.
 */
final class FakeTokenService implements TokenService
{
    public function issue(int $userId, UserRole $role): TokenPair
    {
        return new TokenPair(
            accessToken: 'access-' . $userId . '-' . $role->value,
            refreshToken: 'refresh-' . $userId . '-' . $role->value,
        );
    }

    public function verifyAccess(string $token): TokenClaims
    {
        if (!str_starts_with($token, 'access-')) {
            throw InvalidToken::reason('wrong_type');
        }
        $parts = explode('-', $token);
        if (count($parts) !== 3) {
            throw InvalidToken::reason('malformed');
        }

        return new TokenClaims(
            userId: (int) $parts[1],
            role: UserRole::from($parts[2]),
            type: 'access',
        );
    }
}

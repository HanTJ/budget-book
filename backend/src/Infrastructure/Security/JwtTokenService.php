<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Security;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Application\Auth\TokenPair;
use BudgetBook\Application\Auth\TokenService;
use BudgetBook\Application\Exception\InvalidToken;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Clock\Clock;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

final class JwtTokenService implements TokenService
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $accessTtlSeconds,
        private readonly int $refreshTtlSeconds,
        private readonly Clock $clock,
    ) {
        if (strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 characters.');
        }
    }

    public function issue(int $userId, UserRole $role): TokenPair
    {
        $now = $this->clock->now()->getTimestamp();

        return new TokenPair(
            accessToken: $this->encode($userId, $role, 'access', $now, $now + $this->accessTtlSeconds),
            refreshToken: $this->encode($userId, $role, 'refresh', $now, $now + $this->refreshTtlSeconds),
        );
    }

    public function verifyAccess(string $token): TokenClaims
    {
        $previousTimestamp = JWT::$timestamp;
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $decoded = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (ExpiredException) {
            throw InvalidToken::reason('expired');
        } catch (Throwable $e) {
            throw InvalidToken::reason($e->getMessage());
        } finally {
            JWT::$timestamp = $previousTimestamp;
        }

        $type = property_exists($decoded, 'type') ? (string) $decoded->type : '';
        if ($type !== 'access') {
            throw InvalidToken::reason('wrong_type');
        }

        return new TokenClaims(
            userId: (int) ($decoded->sub ?? 0),
            role: UserRole::from((string) ($decoded->role ?? UserRole::USER->value)),
            type: 'access',
        );
    }

    private function encode(int $userId, UserRole $role, string $type, int $iat, int $exp): string
    {
        return JWT::encode(
            [
                'sub' => $userId,
                'role' => $role->value,
                'type' => $type,
                'iat' => $iat,
                'exp' => $exp,
            ],
            $this->secret,
            self::ALGORITHM,
        );
    }
}

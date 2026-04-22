<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Infrastructure\Security;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Application\Exception\InvalidToken;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Security\JwtTokenService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JwtTokenService::class)]
final class JwtTokenServiceTest extends TestCase
{
    private const SECRET = 'test-secret-test-secret-test-secret-test-secret';

    public function test_round_trip_access_token(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+00:00'));
        $service = new JwtTokenService(self::SECRET, 900, 1209600, $clock);

        $pair = $service->issue(userId: 42, role: UserRole::USER);
        $claims = $service->verifyAccess($pair->accessToken);

        self::assertSame(42, $claims->userId);
        self::assertSame(UserRole::USER, $claims->role);
        self::assertSame('access', $claims->type);
    }

    public function test_rejects_tampered_token(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+00:00'));
        $service = new JwtTokenService(self::SECRET, 900, 1209600, $clock);
        $pair = $service->issue(userId: 1, role: UserRole::USER);

        $this->expectException(InvalidToken::class);
        $service->verifyAccess($pair->accessToken . 'tampered');
    }

    public function test_rejects_expired_access_token(): void
    {
        $issued = new DateTimeImmutable('2026-04-21T09:00:00+00:00');
        $service = new JwtTokenService(self::SECRET, 60, 600, new FixedClock($issued));
        $pair = $service->issue(userId: 1, role: UserRole::USER);

        $laterService = new JwtTokenService(
            self::SECRET,
            60,
            600,
            new FixedClock($issued->modify('+3600 seconds')),
        );

        $this->expectException(InvalidToken::class);
        $laterService->verifyAccess($pair->accessToken);
    }

    public function test_verify_access_rejects_refresh_token(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+00:00'));
        $service = new JwtTokenService(self::SECRET, 900, 1209600, $clock);
        $pair = $service->issue(userId: 1, role: UserRole::USER);

        $this->expectException(InvalidToken::class);
        $service->verifyAccess($pair->refreshToken);
    }

    public function test_claims_expose_role(): void
    {
        $clock = new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+00:00'));
        $service = new JwtTokenService(self::SECRET, 900, 1209600, $clock);
        $pair = $service->issue(userId: 7, role: UserRole::ADMIN);
        $claims = $service->verifyAccess($pair->accessToken);

        self::assertInstanceOf(TokenClaims::class, $claims);
        self::assertSame(UserRole::ADMIN, $claims->role);
    }
}

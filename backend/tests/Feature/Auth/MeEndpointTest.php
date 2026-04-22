<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature\Auth;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\HttpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class MeEndpointTest extends HttpTestCase
{
    public function test_returns_current_user_when_authenticated(): void
    {
        $token = $this->seedAndLogin();

        $response = $this->request('GET', '/api/me', null, $token);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame('me@example.com', $payload['email'] ?? null);
        self::assertSame('USER', $payload['role'] ?? null);
        self::assertSame('ACTIVE', $payload['status'] ?? null);
    }

    public function test_returns_401_without_auth(): void
    {
        $response = $this->request('GET', '/api/me');
        self::assertSame(401, $response->getStatusCode());
    }

    private function seedAndLogin(): string
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of('me@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '미 테스트',
            clock: new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00')),
        );
        $user->activate();
        $repo->save($user);

        $login = $this->request('POST', '/api/auth/login', [
            'email' => 'me@example.com',
            'password' => 'correct-horse-battery',
        ]);
        $payload = $this->json($login);
        return (string) $payload['access_token'];
    }
}

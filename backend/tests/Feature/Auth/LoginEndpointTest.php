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
final class LoginEndpointTest extends HttpTestCase
{
    public function test_active_user_can_login_and_receive_tokens(): void
    {
        $this->seedUser(activate: true);

        $response = $this->request('POST', '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'correct-horse-battery',
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertArrayHasKey('access_token', $payload);
        self::assertArrayHasKey('refresh_token', $payload);
        self::assertIsString($payload['access_token']);
    }

    public function test_pending_user_rejected_with_403_account_pending(): void
    {
        $this->seedUser(activate: false);

        $response = $this->request('POST', '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'correct-horse-battery',
        ]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('account_pending', $this->json($response)['error'] ?? null);
    }

    public function test_wrong_password_rejected_with_401_invalid_credentials(): void
    {
        $this->seedUser(activate: true);

        $response = $this->request('POST', '/api/auth/login', [
            'email' => 'login@example.com',
            'password' => 'wrong-password',
        ]);

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('invalid_credentials', $this->json($response)['error'] ?? null);
    }

    private function seedUser(bool $activate): void
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of('login@example.com'),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '로그인 테스트',
            clock: new FixedClock(new DateTimeImmutable('2026-04-21T09:00:00+09:00')),
        );
        if ($activate) {
            $user->activate();
        }
        $repo->save($user);
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature\Admin;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\HttpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AdminUsersEndpointTest extends HttpTestCase
{
    public function test_non_admin_cannot_access_list(): void
    {
        $token = $this->loginUser(isAdmin: false);
        $response = $this->request('GET', '/api/admin/users', null, $token);
        self::assertSame(403, $response->getStatusCode());
    }

    public function test_admin_can_list_all_users(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $this->seedUser('a@example.com', activate: false);
        $this->seedUser('b@example.com', activate: true);

        $response = $this->request('GET', '/api/admin/users', null, $token);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertGreaterThanOrEqual(3, count($payload['users'] ?? []));
    }

    public function test_admin_can_filter_by_status(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $this->seedUser('pending@example.com', activate: false);
        $this->seedUser('active@example.com', activate: true);

        $response = $this->request('GET', '/api/admin/users?status=PENDING', null, $token);
        $users = $this->json($response)['users'];
        $emails = array_column($users, 'email');
        self::assertContains('pending@example.com', $emails);
        self::assertNotContains('active@example.com', $emails);
    }

    public function test_admin_can_approve_pending_user_and_seed_accounts(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $user = $this->seedUser('approvee@example.com', activate: false);

        $response = $this->request(
            'PATCH',
            "/api/admin/users/{$user['id']}",
            ['status' => 'ACTIVE'],
            $token,
        );
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame('ACTIVE', $payload['status']);

        $accountRows = $this->db->getConnection()
            ->table('accounts')
            ->where('user_id', $user['id'])
            ->count();
        self::assertGreaterThan(10, $accountRows);
    }

    public function test_admin_can_promote_to_admin(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $user = $this->seedUser('future-admin@example.com', activate: true);

        $response = $this->request(
            'PATCH',
            "/api/admin/users/{$user['id']}",
            ['role' => 'ADMIN'],
            $token,
        );
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ADMIN', $this->json($response)['role']);
    }

    public function test_admin_can_soft_delete_user(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $user = $this->seedUser('delete-me@example.com', activate: true);

        $response = $this->request('DELETE', "/api/admin/users/{$user['id']}", null, $token);
        self::assertSame(204, $response->getStatusCode());

        $listResp = $this->request('GET', '/api/admin/users', null, $token);
        $emails = array_column($this->json($listResp)['users'], 'email');
        self::assertNotContains('delete-me@example.com', $emails);
    }

    public function test_missing_user_returns_404(): void
    {
        $token = $this->loginUser(isAdmin: true);
        $response = $this->request('PATCH', '/api/admin/users/999999', ['status' => 'ACTIVE'], $token);
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @return array{id: int, email: string}
     */
    private function seedUser(string $email, bool $activate): array
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: $email,
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        if ($activate) {
            $user->activate();
        }
        $repo->save($user);
        return ['id' => (int) $user->id(), 'email' => $email];
    }

    private function loginUser(bool $isAdmin): string
    {
        $email = $isAdmin ? 'admin-' . uniqid() . '@example.com' : 'user-' . uniqid() . '@example.com';

        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: $isAdmin ? 'Admin' : 'User',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        if ($isAdmin) {
            $user->promoteToAdmin();
        }
        $repo->save($user);

        $login = $this->request('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);
        return (string) $this->json($login)['access_token'];
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature\Ledger;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\HttpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AccountEndpointTest extends HttpTestCase
{
    public function test_list_requires_auth(): void
    {
        $response = $this->request('GET', '/api/accounts');
        self::assertSame(401, $response->getStatusCode());
    }

    public function test_authenticated_user_can_create_and_list_accounts(): void
    {
        $token = $this->seedAndLogin('owner@example.com');

        $create = $this->request('POST', '/api/accounts', [
            'name' => '현금',
            'account_type' => 'ASSET',
            'subtype' => 'CASH',
            'cash_flow_section' => 'NONE',
            'opening_balance' => '10000.00',
        ], $token);
        self::assertSame(201, $create->getStatusCode());
        $created = $this->json($create);
        self::assertSame('현금', $created['name'] ?? null);
        self::assertSame('DEBIT', $created['normal_balance'] ?? null);
        self::assertFalse($created['is_system'] ?? true);

        $list = $this->request('GET', '/api/accounts', null, $token);
        self::assertSame(200, $list->getStatusCode());
        $payload = $this->json($list);
        self::assertArrayHasKey('accounts', $payload);
        self::assertCount(1, $payload['accounts']);
    }

    public function test_income_account_rejects_section_none_with_422(): void
    {
        $token = $this->seedAndLogin('owner@example.com');

        $response = $this->request('POST', '/api/accounts', [
            'name' => '급여',
            'account_type' => 'INCOME',
            'cash_flow_section' => 'NONE',
        ], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('validation_failed', $this->json($response)['error'] ?? null);
    }

    public function test_patch_renames_account(): void
    {
        $token = $this->seedAndLogin('owner@example.com');
        $created = $this->json($this->request('POST', '/api/accounts', [
            'name' => '예전 이름',
            'account_type' => 'ASSET',
            'subtype' => 'BANK',
            'cash_flow_section' => 'NONE',
        ], $token));

        $id = (int) $created['id'];
        $response = $this->request('PATCH', "/api/accounts/{$id}", [
            'name' => '새 이름',
        ], $token);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('새 이름', $this->json($response)['name'] ?? null);
    }

    public function test_delete_performs_soft_delete(): void
    {
        $token = $this->seedAndLogin('owner@example.com');
        $created = $this->json($this->request('POST', '/api/accounts', [
            'name' => '현금',
            'account_type' => 'ASSET',
            'subtype' => 'CASH',
            'cash_flow_section' => 'NONE',
        ], $token));
        $id = (int) $created['id'];

        $delete = $this->request('DELETE', "/api/accounts/{$id}", null, $token);
        self::assertSame(204, $delete->getStatusCode());

        $list = $this->json($this->request('GET', '/api/accounts', null, $token));
        self::assertCount(0, $list['accounts']);
    }

    public function test_cannot_access_other_users_account(): void
    {
        $ownerToken = $this->seedAndLogin('owner@example.com');
        $created = $this->json($this->request('POST', '/api/accounts', [
            'name' => '현금',
            'account_type' => 'ASSET',
            'subtype' => 'CASH',
            'cash_flow_section' => 'NONE',
        ], $ownerToken));
        $id = (int) $created['id'];

        $intruderToken = $this->seedAndLogin('intruder@example.com');

        $response = $this->request('PATCH', "/api/accounts/{$id}", ['name' => '탈취'], $intruderToken);
        self::assertSame(404, $response->getStatusCode());
    }

    private function seedAndLogin(string $email): string
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '사용자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        $repo->save($user);

        $login = $this->request('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);
        $payload = $this->json($login);
        return (string) $payload['access_token'];
    }
}

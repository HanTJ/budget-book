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
final class JournalEntryEndpointTest extends HttpTestCase
{
    public function test_list_requires_auth(): void
    {
        self::assertSame(401, $this->request('GET', '/api/entries')->getStatusCode());
    }

    public function test_cash_expense_round_trip(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $create = $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '12000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
            'merchant' => '분식집',
            'memo' => null,
        ], $token);

        self::assertSame(201, $create->getStatusCode());
        $payload = $this->json($create);
        self::assertIsInt($payload['id'] ?? null);
        self::assertCount(2, $payload['lines'] ?? []);

        $list = $this->request('GET', '/api/entries?from=2026-04-01&to=2026-04-30', null, $token);
        self::assertSame(200, $list->getStatusCode());
        $body = $this->json($list);
        self::assertCount(1, $body['entries'] ?? []);
        self::assertSame('12000.00', $body['entries'][0]['amount']);
    }

    public function test_card_expense_auto_picks_card_liability(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $create = $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '30000',
            'payment_method' => 'CARD',
            'category_account_id' => $accounts['food'],
        ], $token);

        self::assertSame(201, $create->getStatusCode());
        $payload = $this->json($create);

        $creditLine = null;
        foreach ($payload['lines'] as $line) {
            if ((float) $line['credit'] > 0) {
                $creditLine = $line;
            }
        }
        self::assertNotNull($creditLine);
        self::assertSame($accounts['card'], $creditLine['account_id']);
    }

    public function test_validation_error_for_invalid_payload(): void
    {
        [$token] = $this->seedAndLogin();

        $response = $this->request('POST', '/api/entries', [
            'occurred_on' => 'not-a-date',
            'amount' => '-100',
            'payment_method' => 'CASH',
        ], $token);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('validation_failed', $this->json($response)['error'] ?? null);
    }

    public function test_delete_performs_soft_delete(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $create = $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '1000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $token);
        $id = (int) $this->json($create)['id'];

        $delete = $this->request('DELETE', "/api/entries/{$id}", null, $token);
        self::assertSame(204, $delete->getStatusCode());

        $list = $this->request('GET', '/api/entries?from=2026-04-01&to=2026-04-30', null, $token);
        self::assertCount(0, $this->json($list)['entries'] ?? [null]);
    }

    public function test_other_user_cannot_delete(): void
    {
        [$ownerToken, $accounts] = $this->seedAndLogin('owner-a@example.com');
        $create = $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '1000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $ownerToken);
        $id = (int) $this->json($create)['id'];

        [$otherToken] = $this->seedAndLogin('intruder@example.com');
        $delete = $this->request('DELETE', "/api/entries/{$id}", null, $otherToken);
        self::assertSame(404, $delete->getStatusCode());
    }

    /**
     * @return array{0:string,1:array{food:int,cash:int,card:int,bank:int,salary:int}}
     */
    private function seedAndLogin(string $email = 'journaller@example.com'): array
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '거래 사용자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        $repo->save($user);
        $userId = (int) $user->id();

        $accounts = [
            'cash' => $this->insertAccount($userId, '현금', 'ASSET', 'CASH', 'NONE', 'DEBIT'),
            'bank' => $this->insertAccount($userId, '은행', 'ASSET', 'BANK', 'NONE', 'DEBIT'),
            'card' => $this->insertAccount($userId, '카드', 'LIABILITY', 'CARD', 'NONE', 'CREDIT'),
            'food' => $this->insertAccount($userId, '식비', 'EXPENSE', null, 'OPERATING', 'DEBIT'),
            'salary' => $this->insertAccount($userId, '급여', 'INCOME', null, 'OPERATING', 'CREDIT'),
        ];

        $login = $this->request('POST', '/api/auth/login', [
            'email' => $email,
            'password' => 'correct-horse-battery',
        ]);
        $token = (string) $this->json($login)['access_token'];
        return [$token, $accounts];
    }

    private function insertAccount(
        int $userId,
        string $name,
        string $type,
        ?string $subtype,
        string $section,
        string $normalBalance,
    ): int {
        return (int) $this->db->getConnection()->table('accounts')->insertGetId([
            'user_id' => $userId,
            'name' => $name,
            'account_type' => $type,
            'subtype' => $subtype,
            'cash_flow_section' => $section,
            'normal_balance' => $normalBalance,
            'opening_balance' => '0.00',
            'is_system' => 0,
            'created_at' => '2026-04-22 00:00:00',
            'updated_at' => '2026-04-22 00:00:00',
        ]);
    }
}

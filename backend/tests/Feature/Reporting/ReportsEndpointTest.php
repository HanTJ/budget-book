<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature\Reporting;

use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Clock\FixedClock;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Tests\Support\HttpTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class ReportsEndpointTest extends HttpTestCase
{
    public function test_balance_sheet_requires_auth(): void
    {
        self::assertSame(401, $this->request('GET', '/api/reports/balance-sheet?on=2026-04-22')->getStatusCode());
    }

    public function test_balance_sheet_returns_totals_and_identity(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '12000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $token);

        $response = $this->request('GET', '/api/reports/balance-sheet?on=2026-04-22', null, $token);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);

        self::assertArrayHasKey('as_of', $payload);
        self::assertArrayHasKey('total_assets', $payload);
        self::assertArrayHasKey('total_liabilities', $payload);
        self::assertArrayHasKey('total_equity', $payload);
        self::assertArrayHasKey('net_income', $payload);
        self::assertSame(true, $payload['is_balanced'] ?? null);
        self::assertSame('-12000.00', $payload['net_income']);
    }

    public function test_cash_flow_returns_sections(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '3000000',
            'payment_method' => 'TRANSFER',
            'category_account_id' => $accounts['salary'],
            'counter_account_id' => $accounts['bank'],
        ], $token);

        $response = $this->request('GET', '/api/reports/cash-flow?from=2026-04-01&to=2026-04-30', null, $token);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);

        self::assertSame(true, $payload['is_reconciled'] ?? null);
        self::assertSame('3000000.00', $payload['operating']['inflow'] ?? null);
    }

    public function test_daily_report_contains_snapshot_and_entries(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '10000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $token);

        $response = $this->request('GET', '/api/reports/daily?date=2026-04-22', null, $token);
        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);

        self::assertArrayHasKey('balance_sheet', $payload);
        self::assertArrayHasKey('cash_flow', $payload);
        self::assertArrayHasKey('entries', $payload);
        self::assertCount(1, $payload['entries']);
    }

    public function test_validation_error_for_missing_date(): void
    {
        [$token] = $this->seedAndLogin();

        $response = $this->request('GET', '/api/reports/balance-sheet?on=not-a-date', null, $token);
        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * @return array{0:string,1:array{food:int,cash:int,bank:int,salary:int}}
     */
    private function seedAndLogin(string $email = 'reporter@example.com'): array
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '보고자',
            clock: new FixedClock(new DateTimeImmutable('2026-04-22T09:00:00+09:00')),
        );
        $user->activate();
        $repo->save($user);
        $userId = (int) $user->id();

        $accounts = [
            'cash' => $this->insertAccount($userId, '현금', 'ASSET', 'CASH', 'NONE', 'DEBIT'),
            'bank' => $this->insertAccount($userId, '은행', 'ASSET', 'BANK', 'NONE', 'DEBIT'),
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

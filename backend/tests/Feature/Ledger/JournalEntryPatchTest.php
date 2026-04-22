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
final class JournalEntryPatchTest extends HttpTestCase
{
    public function test_patch_updates_amount_and_rebuilds_lines(): void
    {
        [$token, $accounts] = $this->seedAndLogin();

        $created = $this->json($this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '12000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
            'merchant' => '이전 사용처',
        ], $token));
        $id = (int) $created['id'];

        $response = $this->request('PATCH', "/api/entries/{$id}", [
            'occurred_on' => '2026-04-23',
            'amount' => '8000',
            'payment_method' => 'CARD',
            'category_account_id' => $accounts['food'],
            'merchant' => '새 사용처',
        ], $token);

        self::assertSame(200, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame('8000.00', $payload['amount']);
        self::assertSame('CARD', $payload['payment_method']);
        self::assertSame('2026-04-23', $payload['occurred_on']);
        self::assertSame('새 사용처', $payload['merchant']);

        $creditLine = null;
        foreach ($payload['lines'] as $line) {
            if ((float) $line['credit'] > 0) {
                $creditLine = $line;
            }
        }
        self::assertNotNull($creditLine);
        self::assertSame($accounts['card'], $creditLine['account_id']);
    }

    public function test_patch_unknown_entry_returns_404(): void
    {
        [$token] = $this->seedAndLogin();
        $response = $this->request('PATCH', '/api/entries/999999', [
            'occurred_on' => '2026-04-23',
            'amount' => '1',
            'payment_method' => 'CASH',
            'category_account_id' => 1,
        ], $token);
        self::assertSame(404, $response->getStatusCode());
    }

    public function test_other_user_cannot_patch(): void
    {
        [$ownerToken, $accounts] = $this->seedAndLogin('owner@example.com');
        $id = (int) $this->json($this->request('POST', '/api/entries', [
            'occurred_on' => '2026-04-22',
            'amount' => '1000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $ownerToken))['id'];

        [$intruderToken] = $this->seedAndLogin('intruder@example.com');
        $response = $this->request('PATCH', "/api/entries/{$id}", [
            'occurred_on' => '2026-04-23',
            'amount' => '2000',
            'payment_method' => 'CASH',
            'category_account_id' => $accounts['food'],
        ], $intruderToken);

        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @return array{0:string,1:array{food:int,cash:int,card:int,bank:int,salary:int}}
     */
    private function seedAndLogin(string $email = 'patcher@example.com'): array
    {
        $repo = new EloquentUserRepository();
        $user = User::register(
            email: Email::of($email),
            password: HashedPassword::fromPlainText('correct-horse-battery'),
            displayName: '편집자',
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

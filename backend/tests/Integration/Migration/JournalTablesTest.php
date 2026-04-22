<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Migration;

use BudgetBook\Tests\Support\DatabaseTestCase;
use Illuminate\Database\Schema\Builder;
use PDOException;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class JournalTablesTest extends DatabaseTestCase
{
    public function test_journal_entries_has_expected_columns(): void
    {
        self::assertTrue($this->schema()->hasTable('journal_entries'));
        self::assertTrue($this->schema()->hasColumns('journal_entries', [
            'id',
            'user_id',
            'occurred_on',
            'memo',
            'merchant',
            'payment_method',
            'source',
            'deleted_at',
            'created_at',
        ]));
    }

    public function test_journal_entry_lines_has_expected_columns(): void
    {
        self::assertTrue($this->schema()->hasTable('journal_entry_lines'));
        self::assertTrue($this->schema()->hasColumns('journal_entry_lines', [
            'id',
            'entry_id',
            'account_id',
            'debit',
            'credit',
            'line_no',
        ]));
    }

    public function test_check_constraint_rejects_both_debit_and_credit_positive(): void
    {
        $userId = $this->seedUser();
        $accountId = $this->seedAccount($userId);
        $entryId = $this->seedJournalEntry($userId);

        $this->expectException(PDOException::class);
        $this->db->getConnection()->table('journal_entry_lines')->insert([
            'entry_id' => $entryId,
            'account_id' => $accountId,
            'debit' => 100.00,
            'credit' => 50.00,
            'line_no' => 0,
        ]);
    }

    public function test_check_constraint_rejects_both_zero(): void
    {
        $userId = $this->seedUser();
        $accountId = $this->seedAccount($userId);
        $entryId = $this->seedJournalEntry($userId);

        $this->expectException(PDOException::class);
        $this->db->getConnection()->table('journal_entry_lines')->insert([
            'entry_id' => $entryId,
            'account_id' => $accountId,
            'debit' => 0.00,
            'credit' => 0.00,
            'line_no' => 0,
        ]);
    }

    public function test_check_constraint_rejects_negative(): void
    {
        $userId = $this->seedUser();
        $accountId = $this->seedAccount($userId);
        $entryId = $this->seedJournalEntry($userId);

        $this->expectException(PDOException::class);
        $this->db->getConnection()->table('journal_entry_lines')->insert([
            'entry_id' => $entryId,
            'account_id' => $accountId,
            'debit' => -50.00,
            'credit' => 0.00,
            'line_no' => 0,
        ]);
    }

    private function schema(): Builder
    {
        return $this->db->getConnection()->getSchemaBuilder();
    }

    private function seedUser(): int
    {
        return (int) $this->db->getConnection()->table('users')->insertGetId([
            'email' => 'owner-' . uniqid() . '@example.com',
            'password_hash' => '$2y$12$abcdefghijklmnopqrstuv',
            'display_name' => 'Owner',
            'role' => 'USER',
            'status' => 'ACTIVE',
            'created_at' => '2026-04-22 00:00:00',
            'updated_at' => '2026-04-22 00:00:00',
        ]);
    }

    private function seedAccount(int $userId): int
    {
        return (int) $this->db->getConnection()->table('accounts')->insertGetId([
            'user_id' => $userId,
            'name' => '현금',
            'account_type' => 'ASSET',
            'subtype' => 'CASH',
            'cash_flow_section' => 'NONE',
            'normal_balance' => 'DEBIT',
            'opening_balance' => '0.00',
            'is_system' => 0,
            'created_at' => '2026-04-22 00:00:00',
            'updated_at' => '2026-04-22 00:00:00',
        ]);
    }

    private function seedJournalEntry(int $userId): int
    {
        return (int) $this->db->getConnection()->table('journal_entries')->insertGetId([
            'user_id' => $userId,
            'occurred_on' => '2026-04-22',
            'source' => 'USER',
            'created_at' => '2026-04-22 00:00:00',
        ]);
    }
}

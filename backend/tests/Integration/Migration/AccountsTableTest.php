<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Migration;

use BudgetBook\Tests\Support\DatabaseTestCase;
use Illuminate\Database\Schema\Builder;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class AccountsTableTest extends DatabaseTestCase
{
    public function test_accounts_table_has_expected_columns(): void
    {
        self::assertTrue($this->schema()->hasTable('accounts'));
        self::assertTrue($this->schema()->hasColumns('accounts', [
            'id',
            'user_id',
            'name',
            'account_type',
            'subtype',
            'cash_flow_section',
            'normal_balance',
            'opening_balance',
            'is_system',
            'deleted_at',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_accounts_has_user_index(): void
    {
        $indexes = $this->db->getConnection()->select(
            "SHOW INDEX FROM accounts WHERE Column_name = 'user_id'",
        );
        self::assertNotEmpty($indexes);
    }

    public function test_accounts_has_foreign_key_to_users(): void
    {
        $rows = $this->db->getConnection()->select(
            "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'accounts'
               AND COLUMN_NAME = 'user_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL",
        );

        self::assertNotEmpty($rows, 'accounts.user_id must have FK to users(id)');
        $row = (array) $rows[0];
        self::assertSame('users', $row['REFERENCED_TABLE_NAME'] ?? null);
        self::assertSame('id', $row['REFERENCED_COLUMN_NAME'] ?? null);
    }

    private function schema(): Builder
    {
        return $this->db->getConnection()->getSchemaBuilder();
    }
}

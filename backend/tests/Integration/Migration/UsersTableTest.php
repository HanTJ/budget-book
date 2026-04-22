<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Migration;

use BudgetBook\Tests\Support\DatabaseTestCase;
use Illuminate\Database\Schema\Builder;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class UsersTableTest extends DatabaseTestCase
{
    public function test_users_table_has_expected_columns(): void
    {
        $schema = $this->schema();

        self::assertTrue($schema->hasTable('users'));
        self::assertTrue(
            $schema->hasColumns('users', [
                'id',
                'email',
                'password_hash',
                'display_name',
                'role',
                'status',
                'deleted_at',
                'created_at',
                'updated_at',
            ]),
            'users table is missing expected columns',
        );
    }

    public function test_users_email_is_unique(): void
    {
        $connection = $this->db->getConnection();

        $indexes = $connection->select(
            "SHOW INDEX FROM users WHERE Column_name = 'email' AND Non_unique = 0",
        );

        self::assertNotEmpty($indexes, 'email column must have a unique index');
    }

    private function schema(): Builder
    {
        return $this->db->getConnection()->getSchemaBuilder();
    }
}

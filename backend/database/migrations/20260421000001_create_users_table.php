<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateUsersTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('users', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
        ])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('display_name', 'string', ['limit' => 100])
            ->addColumn('role', 'enum', ['values' => ['USER', 'ADMIN'], 'default' => 'USER'])
            ->addColumn('status', 'enum', ['values' => ['PENDING', 'ACTIVE', 'SUSPENDED'], 'default' => 'PENDING'])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uniq_users_email'])
            ->addIndex(['status'])
            ->create();
    }
}

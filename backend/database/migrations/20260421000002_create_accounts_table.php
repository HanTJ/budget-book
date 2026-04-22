<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateAccountsTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('accounts', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
        ])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('account_type', 'enum', [
                'values' => ['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE'],
            ])
            ->addColumn('subtype', 'string', ['limit' => 40, 'null' => true])
            ->addColumn('cash_flow_section', 'enum', [
                'values' => ['OPERATING', 'INVESTING', 'FINANCING', 'NONE'],
                'default' => 'NONE',
            ])
            ->addColumn('normal_balance', 'enum', ['values' => ['DEBIT', 'CREDIT']])
            ->addColumn('opening_balance', 'decimal', [
                'precision' => 18,
                'scale' => 2,
                'default' => '0.00',
            ])
            ->addColumn('is_system', 'boolean', ['default' => false])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addColumn('updated_at', 'datetime', [
                'default' => 'CURRENT_TIMESTAMP',
                'update' => 'CURRENT_TIMESTAMP',
            ])
            ->addIndex(['user_id', 'account_type'])
            ->addIndex(['user_id', 'deleted_at'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_accounts_user',
            ])
            ->create();
    }
}

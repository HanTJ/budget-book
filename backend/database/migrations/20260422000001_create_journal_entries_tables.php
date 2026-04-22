<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateJournalEntriesTables extends AbstractMigration
{
    public function change(): void
    {
        $this->table('journal_entries', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
        ])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('user_id', 'biginteger', ['signed' => false])
            ->addColumn('occurred_on', 'date')
            ->addColumn('memo', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('merchant', 'string', ['limit' => 200, 'null' => true])
            ->addColumn('payment_method', 'enum', [
                'values' => ['CASH', 'CARD', 'TRANSFER'],
                'null' => true,
            ])
            ->addColumn('source', 'enum', [
                'values' => ['USER', 'SYSTEM'],
                'default' => 'USER',
            ])
            ->addColumn('deleted_at', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['user_id', 'occurred_on'])
            ->addIndex(['user_id', 'deleted_at'])
            ->addForeignKey('user_id', 'users', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_journal_entries_user',
            ])
            ->create();

        $this->table('journal_entry_lines', [
            'id' => false,
            'primary_key' => ['id'],
            'engine' => 'InnoDB',
            'collation' => 'utf8mb4_0900_ai_ci',
        ])
            ->addColumn('id', 'biginteger', ['identity' => true, 'signed' => false])
            ->addColumn('entry_id', 'biginteger', ['signed' => false])
            ->addColumn('account_id', 'biginteger', ['signed' => false])
            ->addColumn('debit', 'decimal', [
                'precision' => 18,
                'scale' => 2,
                'default' => '0.00',
            ])
            ->addColumn('credit', 'decimal', [
                'precision' => 18,
                'scale' => 2,
                'default' => '0.00',
            ])
            ->addColumn('line_no', 'smallinteger', ['default' => 0])
            ->addIndex(['entry_id'])
            ->addIndex(['account_id', 'entry_id'])
            ->addForeignKey('entry_id', 'journal_entries', 'id', [
                'delete' => 'CASCADE',
                'update' => 'CASCADE',
                'constraint' => 'fk_lines_entry',
            ])
            ->addForeignKey('account_id', 'accounts', 'id', [
                'delete' => 'RESTRICT',
                'update' => 'CASCADE',
                'constraint' => 'fk_lines_account',
            ])
            ->create();

        $this->execute(
            "ALTER TABLE journal_entry_lines
             ADD CONSTRAINT chk_lines_non_negative
             CHECK (debit >= 0 AND credit >= 0)"
        );
        $this->execute(
            "ALTER TABLE journal_entry_lines
             ADD CONSTRAINT chk_lines_xor
             CHECK ((debit = 0 AND credit > 0) OR (debit > 0 AND credit = 0))"
        );
    }
}

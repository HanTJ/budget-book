<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Integration\Migration;

use BudgetBook\Tests\Support\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Assert that hot-path queries are covered by indexes (EXPLAIN must not show
 * a full table scan via `type = ALL`).
 */
#[CoversNothing]
final class IndexCoverageTest extends DatabaseTestCase
{
    public function test_users_email_lookup_uses_index(): void
    {
        $plan = $this->explain("SELECT id FROM users WHERE email = 'someone@example.com' AND deleted_at IS NULL");
        self::assertNotSame('ALL', $plan['type'] ?? null, 'users.email lookup must use an index');
    }

    public function test_users_status_filter_uses_index(): void
    {
        $plan = $this->explain("SELECT id FROM users WHERE status = 'PENDING' AND deleted_at IS NULL");
        self::assertNotSame('ALL', $plan['type'] ?? null, 'users.status filter must use an index');
    }

    public function test_accounts_by_user_uses_index(): void
    {
        $plan = $this->explain('SELECT id FROM accounts WHERE user_id = 1 AND deleted_at IS NULL');
        self::assertNotSame('ALL', $plan['type'] ?? null, 'accounts lookup by user must use an index');
    }

    public function test_journal_entries_by_user_and_date_uses_index(): void
    {
        $plan = $this->explain(
            "SELECT id FROM journal_entries
             WHERE user_id = 1 AND occurred_on BETWEEN '2026-04-01' AND '2026-04-30'
               AND deleted_at IS NULL"
        );
        self::assertNotSame('ALL', $plan['type'] ?? null, 'journal_entries(user_id, occurred_on) range must use an index');
    }

    public function test_journal_entry_lines_by_account_uses_index(): void
    {
        $plan = $this->explain('SELECT id FROM journal_entry_lines WHERE account_id = 1');
        self::assertNotSame('ALL', $plan['type'] ?? null, 'journal_entry_lines(account_id, ...) must use an index');
    }

    public function test_journal_entry_lines_by_entry_uses_index(): void
    {
        $plan = $this->explain('SELECT id FROM journal_entry_lines WHERE entry_id = 1');
        self::assertNotSame('ALL', $plan['type'] ?? null, 'journal_entry_lines(entry_id) must use an index');
    }

    /**
     * @return array<string, mixed>
     */
    private function explain(string $sql): array
    {
        $rows = $this->db->getConnection()->select('EXPLAIN ' . $sql);
        self::assertNotEmpty($rows, 'EXPLAIN returned no rows');
        /** @var array<string, mixed> $first */
        $first = (array) $rows[0];
        return $first;
    }
}

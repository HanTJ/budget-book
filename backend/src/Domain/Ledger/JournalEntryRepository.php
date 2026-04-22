<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

use DateTimeImmutable;

interface JournalEntryRepository
{
    public function findById(int $id, int $userId): ?JournalEntry;

    /**
     * @return list<JournalEntry>
     */
    public function listForUser(int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    public function save(JournalEntry $entry): void;

    public function softDelete(int $id, int $userId): void;
}

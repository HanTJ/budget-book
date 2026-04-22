<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Support;

use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use DateTimeImmutable;

final class InMemoryJournalEntryRepository implements JournalEntryRepository
{
    /** @var array<int, JournalEntry> */
    private array $byId = [];
    /** @var array<int, true> */
    private array $deleted = [];
    private int $nextId = 1;

    public function findById(int $id, int $userId): ?JournalEntry
    {
        if (isset($this->deleted[$id])) {
            return null;
        }
        $entry = $this->byId[$id] ?? null;
        if ($entry === null || $entry->userId !== $userId) {
            return null;
        }
        return $entry;
    }

    public function listForUser(int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $result = [];
        foreach ($this->byId as $id => $entry) {
            if (isset($this->deleted[$id])) {
                continue;
            }
            if ($entry->userId !== $userId) {
                continue;
            }
            $date = $entry->occurredOn->format('Y-m-d');
            if ($date < $from->format('Y-m-d') || $date > $to->format('Y-m-d')) {
                continue;
            }
            $result[] = $entry;
        }
        usort(
            $result,
            static fn (JournalEntry $a, JournalEntry $b) => $b->occurredOn <=> $a->occurredOn,
        );
        return $result;
    }

    public function save(JournalEntry $entry): void
    {
        if ($entry->id() === null) {
            $id = $this->nextId++;
            $entry->assignId($id);
            $this->byId[$id] = $entry;
            return;
        }
        $this->byId[$entry->id()] = $entry;
    }

    public function softDelete(int $id, int $userId): void
    {
        $entry = $this->byId[$id] ?? null;
        if ($entry === null || $entry->userId !== $userId) {
            return;
        }
        $this->deleted[$id] = true;
    }
}

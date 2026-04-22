<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use BudgetBook\Application\Exception\InvalidJournalEntry;
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryRepository;

final class UpdateJournalEntry
{
    public function __construct(
        private readonly JournalEntryRepository $entries,
        private readonly RecordJournalEntry $record,
    ) {
    }

    /**
     * Editing strategy: record the new entry first, then soft-delete the old one.
     * This keeps the balanced-entry invariant enforced by JournalEntry::record()
     * without a second code path, and keeps the old record intact if validation
     * on the new inputs fails.
     */
    public function handle(int $userId, int $entryId, RecordJournalEntryInput $input): JournalEntry
    {
        $existing = $this->entries->findById($entryId, $userId);
        if ($existing === null) {
            throw InvalidJournalEntry::reason('entry_not_found');
        }

        $replacement = $this->record->handle($input);
        $this->entries->softDelete($entryId, $userId);

        return $replacement;
    }
}

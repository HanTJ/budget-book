<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Persistence\Eloquent;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use BudgetBook\Domain\Ledger\PaymentMethod;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;

final class EloquentJournalEntryRepository implements JournalEntryRepository
{
    private const ENTRIES = 'journal_entries';
    private const LINES = 'journal_entry_lines';

    public function findById(int $id, int $userId): ?JournalEntry
    {
        /** @var object|null $header */
        $header = $this->connection()
            ->table(self::ENTRIES)
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if ($header === null) {
            return null;
        }

        $lines = $this->fetchLines([$id])[$id] ?? [];

        return $this->hydrate((array) $header, $lines);
    }

    public function listForUser(int $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $headers = $this->connection()
            ->table(self::ENTRIES)
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->whereBetween('occurred_on', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();

        if ($headers->isEmpty()) {
            return [];
        }

        $ids = array_values($headers->pluck('id')->map(static fn ($v): int => (int) $v)->all());
        $linesByEntry = $this->fetchLines($ids);

        $result = [];
        foreach ($headers as $row) {
            $data = (array) $row;
            $entryId = (int) ($data['id'] ?? 0);
            $result[] = $this->hydrate($data, $linesByEntry[$entryId] ?? []);
        }
        return $result;
    }

    public function save(JournalEntry $entry): void
    {
        $connection = $this->connection();
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $connection->transaction(function () use ($entry, $connection, $now): void {
            if ($entry->id() === null) {
                $id = (int) $connection->table(self::ENTRIES)->insertGetId([
                    'user_id' => $entry->userId,
                    'occurred_on' => $entry->occurredOn->format('Y-m-d'),
                    'memo' => $entry->memo,
                    'merchant' => $entry->merchant,
                    'payment_method' => $entry->paymentMethod?->value,
                    'source' => $entry->source,
                    'created_at' => $now,
                ]);
                $entry->assignId($id);
            } else {
                $connection->table(self::ENTRIES)->where('id', $entry->id())->update([
                    'occurred_on' => $entry->occurredOn->format('Y-m-d'),
                    'memo' => $entry->memo,
                    'merchant' => $entry->merchant,
                    'payment_method' => $entry->paymentMethod?->value,
                ]);
                $connection->table(self::LINES)->where('entry_id', $entry->id())->delete();
            }

            $rows = [];
            foreach ($entry->lines as $line) {
                $rows[] = [
                    'entry_id' => $entry->id(),
                    'account_id' => $line->accountId,
                    'debit' => (string) $line->debit,
                    'credit' => (string) $line->credit,
                    'line_no' => $line->lineNo,
                ];
            }
            $connection->table(self::LINES)->insert($rows);
        });
    }

    public function softDelete(int $id, int $userId): void
    {
        $this->connection()
            ->table(self::ENTRIES)
            ->where('id', $id)
            ->where('user_id', $userId)
            ->update(['deleted_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    }

    private function connection(): Connection
    {
        return Capsule::connection();
    }

    /**
     * @param list<int> $entryIds
     * @return array<int, list<JournalEntryLine>>
     */
    private function fetchLines(array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $rows = $this->connection()
            ->table(self::LINES)
            ->whereIn('entry_id', $entryIds)
            ->orderBy('entry_id')
            ->orderBy('line_no')
            ->get();

        $byEntry = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $entryId = (int) ($data['entry_id'] ?? 0);
            $debit = BigDecimal::of((string) ($data['debit'] ?? '0'));
            $credit = BigDecimal::of((string) ($data['credit'] ?? '0'));
            $line = $debit->isGreaterThan(BigDecimal::zero())
                ? JournalEntryLine::debit((int) ($data['account_id'] ?? 0), $debit)
                : JournalEntryLine::credit((int) ($data['account_id'] ?? 0), $credit);
            $line->lineNo = (int) ($data['line_no'] ?? 0);
            $byEntry[$entryId][] = $line;
        }
        return $byEntry;
    }

    /**
     * @param array<string, mixed> $header
     * @param list<JournalEntryLine> $lines
     */
    private function hydrate(array $header, array $lines): JournalEntry
    {
        $method = isset($header['payment_method'])
            ? PaymentMethod::from((string) $header['payment_method'])
            : null;

        return JournalEntry::hydrate(
            id: (int) ($header['id'] ?? 0),
            userId: (int) ($header['user_id'] ?? 0),
            occurredOn: new DateTimeImmutable((string) ($header['occurred_on'] ?? 'now')),
            memo: isset($header['memo']) ? (string) $header['memo'] : null,
            merchant: isset($header['merchant']) ? (string) $header['merchant'] : null,
            paymentMethod: $method,
            source: (string) ($header['source'] ?? 'USER'),
            lines: $lines,
        );
    }
}

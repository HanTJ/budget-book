<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use BudgetBook\Domain\Ledger\JournalEntry;

final class JournalEntryPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(JournalEntry $entry): array
    {
        return [
            'id' => $entry->id(),
            'user_id' => $entry->userId,
            'occurred_on' => $entry->occurredOn->format('Y-m-d'),
            'memo' => $entry->memo,
            'merchant' => $entry->merchant,
            'payment_method' => $entry->paymentMethod?->value,
            'source' => $entry->source,
            'amount' => self::format($entry->totalDebit()),
            'lines' => array_map(
                static fn ($line) => [
                    'account_id' => $line->accountId,
                    'debit' => self::format($line->debit),
                    'credit' => self::format($line->credit),
                    'line_no' => $line->lineNo,
                ],
                $entry->lines,
            ),
        ];
    }

    private static function format(BigDecimal $amount): string
    {
        return (string) $amount->toScale(2, RoundingMode::HALF_UP);
    }
}

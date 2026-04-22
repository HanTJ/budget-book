<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use BudgetBook\Domain\Reporting\BalanceSheet;
use BudgetBook\Domain\Reporting\BalanceSheetLine;

final class BalanceSheetPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(BalanceSheet $sheet): array
    {
        return [
            'as_of' => $sheet->asOf->format('Y-m-d'),
            'total_assets' => (string) $sheet->totalAssets(),
            'total_liabilities' => (string) $sheet->totalLiabilities(),
            'total_equity' => (string) $sheet->totalEquity(),
            'net_income' => (string) $sheet->netIncome(),
            'is_balanced' => $sheet->isBalanced(),
            'assets' => array_map(self::line(...), $sheet->assets),
            'liabilities' => array_map(self::line(...), $sheet->liabilities),
            'equity' => array_map(self::line(...), $sheet->equity),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function line(BalanceSheetLine $line): array
    {
        return [
            'account_id' => $line->accountId,
            'name' => $line->name,
            'account_type' => $line->type->value,
            'subtype' => $line->subtype,
            'balance' => (string) $line->balance,
        ];
    }
}

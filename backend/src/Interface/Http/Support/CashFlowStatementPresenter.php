<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use BudgetBook\Domain\Reporting\CashFlowStatement;

final class CashFlowStatementPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(CashFlowStatement $stmt): array
    {
        return [
            'from' => $stmt->from->format('Y-m-d'),
            'to' => $stmt->to->format('Y-m-d'),
            'opening_cash_balance' => (string) $stmt->openingCashBalance,
            'closing_cash_balance' => (string) $stmt->closingCashBalance,
            'is_reconciled' => $stmt->isReconciled(),
            'operating' => [
                'inflow' => (string) $stmt->operatingInflow(),
                'outflow' => (string) $stmt->operatingOutflow(),
                'net' => (string) $stmt->operatingNet(),
            ],
            'investing' => [
                'inflow' => (string) $stmt->investingInflow(),
                'outflow' => (string) $stmt->investingOutflow(),
                'net' => (string) $stmt->investingNet(),
            ],
            'financing' => [
                'inflow' => (string) $stmt->financingInflow(),
                'outflow' => (string) $stmt->financingOutflow(),
                'net' => (string) $stmt->financingNet(),
            ],
            'net_change' => (string) $stmt->netChange(),
        ];
    }
}

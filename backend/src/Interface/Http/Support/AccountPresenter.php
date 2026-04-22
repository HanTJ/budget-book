<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use BudgetBook\Domain\Ledger\Account;

final class AccountPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(Account $account): array
    {
        return [
            'id' => $account->id(),
            'user_id' => $account->userId,
            'name' => $account->name,
            'account_type' => $account->type->value,
            'subtype' => $account->subtype,
            'cash_flow_section' => $account->cashFlowSection->value,
            'normal_balance' => $account->normalBalance->value,
            'opening_balance' => (string) $account->openingBalance,
            'is_system' => $account->isSystem,
        ];
    }
}

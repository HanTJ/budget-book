<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use DomainException;

final class CreateAccount
{
    public function __construct(private readonly AccountRepository $accounts)
    {
    }

    /**
     * @throws DomainException
     */
    public function handle(
        int $userId,
        string $name,
        AccountType $type,
        ?string $subtype,
        CashFlowSection $section,
        ?string $openingBalance,
    ): Account {
        $account = Account::create(
            userId: $userId,
            name: $name,
            type: $type,
            subtype: $subtype,
            section: $section,
            openingBalance: $openingBalance !== null && $openingBalance !== ''
                ? BigDecimal::of($openingBalance)
                : null,
        );
        $this->accounts->save($account);
        return $account;
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;

final class SeedDefaultAccounts
{
    public function __construct(private readonly AccountRepository $accounts)
    {
    }

    public function seed(int $userId): void
    {
        if ($this->accounts->listForUser($userId) !== []) {
            return;
        }

        foreach (self::template() as [$name, $type, $subtype, $section]) {
            $account = Account::create(
                userId: $userId,
                name: $name,
                type: $type,
                subtype: $subtype,
                section: $section,
            )->markAsSystem();
            $this->accounts->save($account);
        }
    }

    /**
     * @return list<array{0:string,1:AccountType,2:?string,3:CashFlowSection}>
     */
    private static function template(): array
    {
        return [
            // ASSET
            ['현금', AccountType::ASSET, 'CASH', CashFlowSection::NONE],
            ['주거래은행', AccountType::ASSET, 'BANK', CashFlowSection::NONE],
            ['증권계좌', AccountType::ASSET, 'INVESTMENT', CashFlowSection::NONE],
            // LIABILITY
            ['신용카드', AccountType::LIABILITY, 'CARD', CashFlowSection::NONE],
            ['대출', AccountType::LIABILITY, 'LOAN', CashFlowSection::NONE],
            // EQUITY
            ['자본금', AccountType::EQUITY, null, CashFlowSection::NONE],
            ['이익잉여금', AccountType::EQUITY, null, CashFlowSection::NONE],
            // INCOME — OPERATING
            ['급여', AccountType::INCOME, null, CashFlowSection::OPERATING],
            ['상여금', AccountType::INCOME, null, CashFlowSection::OPERATING],
            ['사업소득', AccountType::INCOME, null, CashFlowSection::OPERATING],
            ['이자수입', AccountType::INCOME, null, CashFlowSection::OPERATING],
            ['기타수입', AccountType::INCOME, null, CashFlowSection::OPERATING],
            // INCOME — INVESTING
            ['배당수입', AccountType::INCOME, null, CashFlowSection::INVESTING],
            ['투자수익(실현)', AccountType::INCOME, null, CashFlowSection::INVESTING],
            ['예적금 해지수입', AccountType::INCOME, null, CashFlowSection::INVESTING],
            // INCOME — FINANCING
            ['대출수령', AccountType::INCOME, null, CashFlowSection::FINANCING],
            // EXPENSE — OPERATING
            ['식비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['주거비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['교통비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['통신비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['공과금', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['의료비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['교육비', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['의류·미용', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['여가·문화', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            ['기타지출', AccountType::EXPENSE, null, CashFlowSection::OPERATING],
            // EXPENSE — FINANCING (이자 납부)
            ['대출이자', AccountType::EXPENSE, null, CashFlowSection::FINANCING],
        ];
    }
}

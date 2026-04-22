<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use BudgetBook\Domain\Ledger\NormalBalance;
use DateTimeImmutable;
use LogicException;

final class BalanceSheetService
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly JournalEntryRepository $entries,
    ) {
    }

    public function compute(int $userId, DateTimeImmutable $asOf): BalanceSheet
    {
        $accounts = $this->accounts->listForUser($userId);

        $beginning = new DateTimeImmutable('1970-01-01');
        $entriesInRange = $this->entries->listForUser($userId, $beginning, $asOf);

        /** @var array<int, BigDecimal> $debitTotals */
        $debitTotals = [];
        /** @var array<int, BigDecimal> $creditTotals */
        $creditTotals = [];

        foreach ($entriesInRange as $entry) {
            foreach ($entry->lines as $line) {
                $debitTotals[$line->accountId] = ($debitTotals[$line->accountId] ?? BigDecimal::zero())
                    ->plus($line->debit);
                $creditTotals[$line->accountId] = ($creditTotals[$line->accountId] ?? BigDecimal::zero())
                    ->plus($line->credit);
            }
        }

        $assets = [];
        $liabilities = [];
        $equity = [];
        $totalIncome = BigDecimal::zero();
        $totalExpense = BigDecimal::zero();
        $assetOpening = BigDecimal::zero();
        $liabilityOpening = BigDecimal::zero();
        $equityOpening = BigDecimal::zero();

        foreach ($accounts as $account) {
            $id = (int) $account->id();
            $debits = $debitTotals[$id] ?? BigDecimal::zero();
            $credits = $creditTotals[$id] ?? BigDecimal::zero();

            $balance = $this->balanceFor($account, $debits, $credits);
            $line = new BalanceSheetLine(
                accountId: $id,
                name: $account->name,
                type: $account->type,
                subtype: $account->subtype,
                balance: $balance,
            );

            switch ($account->type) {
                case AccountType::ASSET:
                    $assets[] = $line;
                    $assetOpening = $assetOpening->plus($account->openingBalance);
                    break;
                case AccountType::LIABILITY:
                    $liabilities[] = $line;
                    $liabilityOpening = $liabilityOpening->plus($account->openingBalance);
                    break;
                case AccountType::EQUITY:
                    $equity[] = $line;
                    $equityOpening = $equityOpening->plus($account->openingBalance);
                    break;
                case AccountType::INCOME:
                    $totalIncome = $totalIncome->plus($balance);
                    break;
                case AccountType::EXPENSE:
                    $totalExpense = $totalExpense->plus($balance);
                    break;
            }
        }

        // Reconcile opening balances that lacked an explicit offsetting equity entry.
        // Represents the unrecorded "owner's capital" behind initial asset/liability figures.
        $implicitOpeningEquity = $assetOpening->minus($liabilityOpening)->minus($equityOpening);
        if (!$implicitOpeningEquity->isEqualTo(BigDecimal::zero())) {
            $equity[] = new BalanceSheetLine(
                accountId: 0,
                name: '개시자본(초기 잔액)',
                type: AccountType::EQUITY,
                subtype: null,
                balance: $implicitOpeningEquity,
            );
        }

        $netIncome = $totalIncome->minus($totalExpense);

        $sheet = new BalanceSheet(
            asOf: $asOf,
            assets: $assets,
            liabilities: $liabilities,
            equity: $equity,
            netIncomeValue: $netIncome,
        );

        if (!$sheet->isBalanced()) {
            throw new LogicException(sprintf(
                'Balance sheet identity violated: assets=%s liab+equity=%s',
                (string) $sheet->totalAssets(),
                (string) $sheet->totalLiabilities()->plus($sheet->totalEquity()),
            ));
        }

        return $sheet;
    }

    private function balanceFor(Account $account, BigDecimal $debits, BigDecimal $credits): BigDecimal
    {
        $net = $account->normalBalance === NormalBalance::DEBIT
            ? $debits->minus($credits)
            : $credits->minus($debits);
        return $account->openingBalance->plus($net);
    }
}

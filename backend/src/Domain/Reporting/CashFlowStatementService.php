<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use DateTimeImmutable;
use LogicException;

final class CashFlowStatementService
{
    private const CASH_SUBTYPES = ['CASH', 'BANK'];

    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly JournalEntryRepository $entries,
    ) {
    }

    public function compute(int $userId, DateTimeImmutable $from, DateTimeImmutable $to): CashFlowStatement
    {
        /** @var array<int, Account> $byId */
        $byId = [];
        foreach ($this->accounts->listForUser($userId) as $account) {
            $byId[(int) $account->id()] = $account;
        }

        $cashAccountIds = [];
        foreach ($byId as $id => $account) {
            if ($this->isCash($account)) {
                $cashAccountIds[$id] = true;
            }
        }

        $opening = $this->cashBalanceUpTo($userId, $byId, $cashAccountIds, $from, inclusive: false);
        $closing = $this->cashBalanceUpTo($userId, $byId, $cashAccountIds, $to, inclusive: true);

        $periodEntries = $this->entries->listForUser($userId, $from, $to);

        $opInflow = BigDecimal::zero();
        $opOutflow = BigDecimal::zero();
        $invInflow = BigDecimal::zero();
        $invOutflow = BigDecimal::zero();
        $finInflow = BigDecimal::zero();
        $finOutflow = BigDecimal::zero();

        foreach ($periodEntries as $entry) {
            /** @var list<JournalEntryLine> $cashLines */
            $cashLines = [];
            /** @var list<JournalEntryLine> $nonCashLines */
            $nonCashLines = [];
            foreach ($entry->lines as $line) {
                if (isset($cashAccountIds[$line->accountId])) {
                    $cashLines[] = $line;
                } else {
                    $nonCashLines[] = $line;
                }
            }
            if ($cashLines === [] || $nonCashLines === []) {
                continue;
            }

            foreach ($nonCashLines as $line) {
                $account = $byId[$line->accountId] ?? null;
                if ($account === null || $account->cashFlowSection === CashFlowSection::NONE) {
                    continue;
                }
                $amount = $line->amount();
                $isOutflow = $line->isDebit(); // non-cash debit => cash credited (outflow)

                if ($account->cashFlowSection === CashFlowSection::OPERATING) {
                    if ($isOutflow) {
                        $opOutflow = $opOutflow->plus($amount);
                    } else {
                        $opInflow = $opInflow->plus($amount);
                    }
                } elseif ($account->cashFlowSection === CashFlowSection::INVESTING) {
                    if ($isOutflow) {
                        $invOutflow = $invOutflow->plus($amount);
                    } else {
                        $invInflow = $invInflow->plus($amount);
                    }
                } else {
                    if ($isOutflow) {
                        $finOutflow = $finOutflow->plus($amount);
                    } else {
                        $finInflow = $finInflow->plus($amount);
                    }
                }
            }
        }

        $sections = [
            CashFlowSection::OPERATING->value => ['inflow' => $opInflow, 'outflow' => $opOutflow],
            CashFlowSection::INVESTING->value => ['inflow' => $invInflow, 'outflow' => $invOutflow],
            CashFlowSection::FINANCING->value => ['inflow' => $finInflow, 'outflow' => $finOutflow],
        ];

        $statement = new CashFlowStatement(
            from: $from,
            to: $to,
            openingCashBalance: $opening,
            closingCashBalance: $closing,
            sections: $sections,
        );

        if (!$statement->isReconciled()) {
            throw new LogicException(sprintf(
                'Cash flow reconciliation failed: sections=%s cash delta=%s',
                (string) $statement->netChange(),
                (string) $closing->minus($opening),
            ));
        }

        return $statement;
    }

    private function isCash(Account $account): bool
    {
        return $account->type === AccountType::ASSET
            && $account->subtype !== null
            && in_array($account->subtype, self::CASH_SUBTYPES, true);
    }

    /**
     * @param array<int, Account> $byId
     * @param array<int, true> $cashAccountIds
     */
    private function cashBalanceUpTo(
        int $userId,
        array $byId,
        array $cashAccountIds,
        DateTimeImmutable $asOf,
        bool $inclusive,
    ): BigDecimal {
        $opening = BigDecimal::zero();
        foreach ($byId as $account) {
            if ($this->isCash($account)) {
                $opening = $opening->plus($account->openingBalance);
            }
        }

        $from = new DateTimeImmutable('1970-01-01');
        $upper = $inclusive ? $asOf : $asOf->modify('-1 day');

        if ($upper < $from) {
            return $opening;
        }

        $entries = $this->entries->listForUser($userId, $from, $upper);
        $delta = BigDecimal::zero();
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                if (!isset($cashAccountIds[$line->accountId])) {
                    continue;
                }
                $delta = $delta->plus($line->debit)->minus($line->credit);
            }
        }

        return $opening->plus($delta);
    }

}

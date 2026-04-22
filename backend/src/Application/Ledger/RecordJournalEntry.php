<?php

declare(strict_types=1);

namespace BudgetBook\Application\Ledger;

use Brick\Math\BigDecimal;
use Brick\Math\Exception\NumberFormatException;
use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Application\Exception\InvalidJournalEntry;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\JournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryLine;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use BudgetBook\Domain\Ledger\PaymentMethod;
use DateTimeImmutable;
use DomainException;
use Throwable;

final class RecordJournalEntry
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly JournalEntryRepository $entries,
    ) {
    }

    public function handle(RecordJournalEntryInput $input): JournalEntry
    {
        try {
            $amount = BigDecimal::of($input->amount);
        } catch (NumberFormatException) {
            throw InvalidJournalEntry::reason('amount is not numeric');
        }
        if (!$amount->isPositive()) {
            throw InvalidJournalEntry::reason('amount must be greater than zero');
        }

        try {
            $occurredOn = new DateTimeImmutable($input->occurredOn);
        } catch (Throwable) {
            throw InvalidJournalEntry::reason('occurred_on must be a valid date');
        }

        $category = $this->requireOwnedAccount($input->categoryAccountId, $input->userId);

        [$debitAccountId, $creditAccountId] = $this->resolveLegs($category, $input);

        try {
            $entry = JournalEntry::record(
                userId: $input->userId,
                occurredOn: $occurredOn,
                memo: $input->memo,
                merchant: $input->merchant,
                paymentMethod: $input->paymentMethod,
                lines: [
                    JournalEntryLine::debit($debitAccountId, $amount),
                    JournalEntryLine::credit($creditAccountId, $amount),
                ],
            );
        } catch (DomainException $e) {
            throw InvalidJournalEntry::reason($e->getMessage());
        }

        $this->entries->save($entry);

        return $entry;
    }

    /**
     * @return array{int, int} [debit_account_id, credit_account_id]
     */
    private function resolveLegs(Account $category, RecordJournalEntryInput $input): array
    {
        return match ($category->type) {
            AccountType::EXPENSE => [(int) $category->id(), $this->resolveCounterForOutflow($input, $category->userId)],
            AccountType::INCOME => [$this->resolveCounterForInflow($input, $category->userId), (int) $category->id()],
            default => throw InvalidJournalEntry::reason(
                'Phase 3 supports EXPENSE/INCOME categories only; got ' . $category->type->value,
            ),
        };
    }

    private function resolveCounterForOutflow(RecordJournalEntryInput $input, int $userId): int
    {
        if ($input->counterAccountId !== null) {
            $counter = $this->requireOwnedAccount($input->counterAccountId, $userId);
            if ($counter->type !== AccountType::ASSET && $counter->type !== AccountType::LIABILITY) {
                throw InvalidJournalEntry::reason('counter account for expense must be ASSET or LIABILITY');
            }
            return (int) $counter->id();
        }

        return match ($input->paymentMethod) {
            PaymentMethod::CASH => $this->autoPickAssetBySubtype($userId, 'CASH'),
            PaymentMethod::CARD => $this->autoPickLiabilityBySubtype($userId, 'CARD'),
            PaymentMethod::TRANSFER => throw InvalidJournalEntry::reason(
                'TRANSFER payment requires an explicit counter_account_id',
            ),
        };
    }

    private function resolveCounterForInflow(RecordJournalEntryInput $input, int $userId): int
    {
        if ($input->paymentMethod === PaymentMethod::CARD) {
            throw InvalidJournalEntry::reason('INCOME cannot be received via CARD');
        }

        if ($input->counterAccountId !== null) {
            $counter = $this->requireOwnedAccount($input->counterAccountId, $userId);
            if ($counter->type !== AccountType::ASSET) {
                throw InvalidJournalEntry::reason('counter account for income must be ASSET');
            }
            return (int) $counter->id();
        }

        return match ($input->paymentMethod) {
            PaymentMethod::CASH => $this->autoPickAssetBySubtype($userId, 'CASH'),
            PaymentMethod::TRANSFER => throw InvalidJournalEntry::reason(
                'TRANSFER income requires an explicit counter_account_id',
            ),
        };
    }

    private function requireOwnedAccount(int $accountId, int $userId): Account
    {
        $account = $this->accounts->findById($accountId);
        if ($account === null || $account->userId !== $userId) {
            throw AccountNotFound::forId($accountId);
        }
        return $account;
    }

    private function autoPickAssetBySubtype(int $userId, string $subtype): int
    {
        foreach ($this->accounts->listForUser($userId) as $account) {
            if ($account->type === AccountType::ASSET && $account->subtype === $subtype) {
                return (int) $account->id();
            }
        }
        throw InvalidJournalEntry::reason("no ASSET account with subtype={$subtype}");
    }

    private function autoPickLiabilityBySubtype(int $userId, string $subtype): int
    {
        foreach ($this->accounts->listForUser($userId) as $account) {
            if ($account->type === AccountType::LIABILITY && $account->subtype === $subtype) {
                return (int) $account->id();
            }
        }
        throw InvalidJournalEntry::reason("no LIABILITY account with subtype={$subtype}");
    }
}

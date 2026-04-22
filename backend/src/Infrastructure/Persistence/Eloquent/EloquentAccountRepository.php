<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Persistence\Eloquent;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\Account;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Domain\Ledger\NormalBalance;
use DateTimeImmutable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;

final class EloquentAccountRepository implements AccountRepository
{
    private const TABLE = 'accounts';

    public function findById(int $id): ?Account
    {
        /** @var object|null $row */
        $row = $this->table()
            ->whereNull('deleted_at')
            ->where('id', $id)
            ->first();

        return $this->hydrate($row);
    }

    public function listForUser(int $userId): array
    {
        $rows = $this->table()
            ->whereNull('deleted_at')
            ->where('user_id', $userId)
            ->orderBy('account_type')
            ->orderBy('id')
            ->get();

        $accounts = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrate($row);
            if ($hydrated !== null) {
                $accounts[] = $hydrated;
            }
        }
        return $accounts;
    }

    public function save(Account $account): void
    {
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $payload = [
            'user_id' => $account->userId,
            'name' => $account->name,
            'account_type' => $account->type->value,
            'subtype' => $account->subtype,
            'cash_flow_section' => $account->cashFlowSection->value,
            'normal_balance' => $account->normalBalance->value,
            'opening_balance' => (string) $account->openingBalance,
            'is_system' => $account->isSystem ? 1 : 0,
            'updated_at' => $now,
        ];

        if ($account->id() === null) {
            $payload['created_at'] = $now;
            $id = (int) $this->table()->insertGetId($payload);
            $account->assignId($id);
            return;
        }

        $this->table()->where('id', $account->id())->update($payload);
    }

    public function softDelete(int $id): void
    {
        $this->table()
            ->where('id', $id)
            ->update(['deleted_at' => (new DateTimeImmutable('now'))->format('Y-m-d H:i:s')]);
    }

    private function table(): Builder
    {
        return Capsule::connection()->table(self::TABLE);
    }

    private function hydrate(?object $row): ?Account
    {
        if ($row === null) {
            return null;
        }
        /** @var array<string, mixed> $data */
        $data = (array) $row;

        return Account::hydrate(
            id: (int) ($data['id'] ?? 0),
            userId: (int) ($data['user_id'] ?? 0),
            name: (string) ($data['name'] ?? ''),
            type: AccountType::from((string) ($data['account_type'] ?? AccountType::ASSET->value)),
            subtype: isset($data['subtype']) ? (string) $data['subtype'] : null,
            section: CashFlowSection::from((string) ($data['cash_flow_section'] ?? CashFlowSection::NONE->value)),
            normalBalance: NormalBalance::from((string) ($data['normal_balance'] ?? NormalBalance::DEBIT->value)),
            openingBalance: BigDecimal::of((string) ($data['opening_balance'] ?? '0')),
            isSystem: (bool) ($data['is_system'] ?? false),
        );
    }
}

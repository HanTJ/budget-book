<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Ledger;

use Brick\Math\BigDecimal;
use DomainException;

final class Account
{
    private function __construct(
        private ?int $id,
        public readonly int $userId,
        public string $name,
        public readonly AccountType $type,
        public readonly ?string $subtype,
        public readonly CashFlowSection $cashFlowSection,
        public readonly NormalBalance $normalBalance,
        public readonly BigDecimal $openingBalance,
        public readonly bool $isSystem,
    ) {
    }

    public static function create(
        int $userId,
        string $name,
        AccountType $type,
        ?string $subtype,
        CashFlowSection $section,
        ?BigDecimal $openingBalance = null,
    ): self {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new DomainException('Account name must not be blank.');
        }
        if (mb_strlen($trimmed) > 100) {
            throw new DomainException('Account name exceeds 100 characters.');
        }

        if ($type->requiresCashFlowSection() && $section === CashFlowSection::NONE) {
            throw new DomainException(sprintf(
                '%s accounts must declare a cash flow section.',
                $type->value,
            ));
        }

        if (!$type->requiresCashFlowSection() && $section !== CashFlowSection::NONE
            && $type !== AccountType::ASSET && $type !== AccountType::LIABILITY
        ) {
            throw new DomainException(sprintf(
                '%s accounts must not declare a cash flow section.',
                $type->value,
            ));
        }

        $opening = $openingBalance ?? BigDecimal::zero();
        if ($opening->isNegative()) {
            throw new DomainException('Opening balance must not be negative.');
        }

        return new self(
            id: null,
            userId: $userId,
            name: $trimmed,
            type: $type,
            subtype: $subtype,
            cashFlowSection: $section,
            normalBalance: $type->defaultNormalBalance(),
            openingBalance: $opening,
            isSystem: false,
        );
    }

    public static function hydrate(
        int $id,
        int $userId,
        string $name,
        AccountType $type,
        ?string $subtype,
        CashFlowSection $section,
        NormalBalance $normalBalance,
        BigDecimal $openingBalance,
        bool $isSystem,
    ): self {
        return new self(
            id: $id,
            userId: $userId,
            name: $name,
            type: $type,
            subtype: $subtype,
            cashFlowSection: $section,
            normalBalance: $normalBalance,
            openingBalance: $openingBalance,
            isSystem: $isSystem,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function assignId(int $id): void
    {
        if ($this->id !== null) {
            throw new DomainException('Account id already assigned.');
        }
        $this->id = $id;
    }

    public function rename(string $name): void
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            throw new DomainException('Account name must not be blank.');
        }
        $this->name = $trimmed;
    }

    public function markAsSystem(): self
    {
        return new self(
            id: $this->id,
            userId: $this->userId,
            name: $this->name,
            type: $this->type,
            subtype: $this->subtype,
            cashFlowSection: $this->cashFlowSection,
            normalBalance: $this->normalBalance,
            openingBalance: $this->openingBalance,
            isSystem: true,
        );
    }
}

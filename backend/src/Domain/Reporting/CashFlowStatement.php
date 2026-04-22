<?php

declare(strict_types=1);

namespace BudgetBook\Domain\Reporting;

use Brick\Math\BigDecimal;
use BudgetBook\Domain\Ledger\CashFlowSection;
use DateTimeImmutable;

final class CashFlowStatement
{
    /**
     * @param array<string, array{inflow: BigDecimal, outflow: BigDecimal}> $sections
     */
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly BigDecimal $openingCashBalance,
        public readonly BigDecimal $closingCashBalance,
        private readonly array $sections,
    ) {
    }

    public function operatingInflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::OPERATING->value]['inflow'];
    }

    public function operatingOutflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::OPERATING->value]['outflow'];
    }

    public function operatingNet(): BigDecimal
    {
        return $this->operatingInflow()->minus($this->operatingOutflow());
    }

    public function investingInflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::INVESTING->value]['inflow'];
    }

    public function investingOutflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::INVESTING->value]['outflow'];
    }

    public function investingNet(): BigDecimal
    {
        return $this->investingInflow()->minus($this->investingOutflow());
    }

    public function financingInflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::FINANCING->value]['inflow'];
    }

    public function financingOutflow(): BigDecimal
    {
        return $this->sections[CashFlowSection::FINANCING->value]['outflow'];
    }

    public function financingNet(): BigDecimal
    {
        return $this->financingInflow()->minus($this->financingOutflow());
    }

    public function netChange(): BigDecimal
    {
        return $this->operatingNet()->plus($this->investingNet())->plus($this->financingNet());
    }

    /**
     * Σ(3 sections) must equal closing − opening cash balance.
     */
    public function isReconciled(): bool
    {
        $expected = $this->closingCashBalance->minus($this->openingCashBalance);
        return $this->netChange()->isEqualTo($expected);
    }
}

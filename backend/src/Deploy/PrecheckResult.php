<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final readonly class PrecheckResult
{
    /**
     * @param list<PrecheckCheck> $checks
     */
    public function __construct(public array $checks)
    {
    }

    public function isOk(): bool
    {
        foreach ($this->checks as $check) {
            if (!$check->passed) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return list<PrecheckCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->checks,
            static fn (PrecheckCheck $c): bool => !$c->passed,
        ));
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Validation;

use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;

final class AccountValidator
{
    /**
     * @return array<string, string>
     */
    public function validateCreate(mixed $payload): array
    {
        if (!is_array($payload)) {
            return ['_root' => 'Request body must be a JSON object.'];
        }

        $errors = [];

        $name = $payload['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen(trim($name)) > 100) {
            $errors['name'] = 'Name must be 100 characters or fewer.';
        }

        $type = $payload['account_type'] ?? null;
        if (!is_string($type) || AccountType::tryFrom($type) === null) {
            $errors['account_type'] = 'account_type must be one of ASSET/LIABILITY/EQUITY/INCOME/EXPENSE.';
        } else {
            $accountType = AccountType::from($type);

            $section = $payload['cash_flow_section'] ?? 'NONE';
            if (!is_string($section) || CashFlowSection::tryFrom($section) === null) {
                $errors['cash_flow_section'] = 'cash_flow_section must be one of OPERATING/INVESTING/FINANCING/NONE.';
            } elseif ($accountType->requiresCashFlowSection()
                && CashFlowSection::from($section) === CashFlowSection::NONE
            ) {
                $errors['cash_flow_section'] = 'INCOME and EXPENSE accounts require a cash flow section.';
            }
        }

        $opening = $payload['opening_balance'] ?? null;
        if ($opening !== null && !is_string($opening) && !is_numeric($opening)) {
            $errors['opening_balance'] = 'opening_balance must be a numeric string.';
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public function validateRename(mixed $payload): array
    {
        if (!is_array($payload)) {
            return ['_root' => 'Request body must be a JSON object.'];
        }
        $name = $payload['name'] ?? null;
        if (!is_string($name) || trim($name) === '') {
            return ['name' => 'Name is required.'];
        }
        if (mb_strlen(trim($name)) > 100) {
            return ['name' => 'Name must be 100 characters or fewer.'];
        }
        return [];
    }
}

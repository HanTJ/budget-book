<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Validation;

use BudgetBook\Domain\Ledger\PaymentMethod;

final class JournalEntryValidator
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

        $date = $payload['occurred_on'] ?? null;
        if (!is_string($date) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            $errors['occurred_on'] = 'occurred_on must be a date in YYYY-MM-DD format.';
        }

        $amount = $payload['amount'] ?? null;
        if (
            !is_string($amount) && !is_int($amount) && !is_float($amount)
            || !preg_match('/^\d+(\.\d{1,2})?$/', (string) $amount)
            || (float) $amount <= 0.0
        ) {
            $errors['amount'] = 'amount must be a positive decimal (up to 2 fractional digits).';
        }

        $method = $payload['payment_method'] ?? null;
        if (!is_string($method) || PaymentMethod::tryFrom($method) === null) {
            $errors['payment_method'] = 'payment_method must be one of CASH/CARD/TRANSFER.';
        }

        if (!isset($payload['category_account_id']) || !is_int($payload['category_account_id'])) {
            $errors['category_account_id'] = 'category_account_id must be an integer.';
        }

        if (array_key_exists('counter_account_id', $payload) && $payload['counter_account_id'] !== null) {
            if (!is_int($payload['counter_account_id'])) {
                $errors['counter_account_id'] = 'counter_account_id must be an integer or null.';
            }
        }

        return $errors;
    }
}

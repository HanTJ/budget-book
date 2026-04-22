<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Validation;

final class RegisterValidator
{
    /**
     * @param mixed $payload
     * @return array<string, string> errors keyed by field
     */
    public function validate(mixed $payload): array
    {
        $errors = [];

        if (!is_array($payload)) {
            return ['_root' => 'Request body must be a JSON object.'];
        }

        $email = $payload['email'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            $errors['email'] = 'Email is required.';
        } elseif (filter_var(trim($email), FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Email format is invalid.';
        }

        $password = $payload['password'] ?? null;
        if (!is_string($password) || strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        }

        $displayName = $payload['display_name'] ?? null;
        if (!is_string($displayName) || trim($displayName) === '') {
            $errors['display_name'] = 'Display name is required.';
        } elseif (mb_strlen(trim($displayName)) > 100) {
            $errors['display_name'] = 'Display name must be 100 characters or fewer.';
        }

        return $errors;
    }
}

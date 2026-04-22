<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Validation;

final class LoginValidator
{
    /**
     * @return array<string, string>
     */
    public function validate(mixed $payload): array
    {
        if (!is_array($payload)) {
            return ['_root' => 'Request body must be a JSON object.'];
        }

        $errors = [];

        $email = $payload['email'] ?? null;
        if (!is_string($email) || trim($email) === '') {
            $errors['email'] = 'Email is required.';
        }

        $password = $payload['password'] ?? null;
        if (!is_string($password) || $password === '') {
            $errors['password'] = 'Password is required.';
        }

        return $errors;
    }
}

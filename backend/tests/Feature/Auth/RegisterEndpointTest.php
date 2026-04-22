<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature\Auth;

use BudgetBook\Tests\Support\HttpTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class RegisterEndpointTest extends HttpTestCase
{
    public function test_registers_new_user_and_returns_201(): void
    {
        $response = $this->request('POST', '/api/auth/register', [
            'email' => 'new@example.com',
            'password' => 'correct-horse-battery',
            'display_name' => '새 사용자',
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $this->json($response);

        self::assertSame('new@example.com', $payload['email'] ?? null);
        self::assertSame('PENDING', $payload['status'] ?? null);
        self::assertIsInt($payload['id'] ?? null);
    }

    public function test_rejects_duplicate_email_with_409(): void
    {
        $this->request('POST', '/api/auth/register', [
            'email' => 'dup@example.com',
            'password' => 'correct-horse-battery',
            'display_name' => '중복',
        ]);

        $response = $this->request('POST', '/api/auth/register', [
            'email' => 'DUP@example.com',
            'password' => 'correct-horse-battery',
            'display_name' => '중복2',
        ]);

        self::assertSame(409, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame('email_already_registered', $payload['error'] ?? null);
    }

    public function test_rejects_invalid_payload_with_422(): void
    {
        $response = $this->request('POST', '/api/auth/register', [
            'email' => 'not-an-email',
            'password' => 'short',
            'display_name' => '',
        ]);

        self::assertSame(422, $response->getStatusCode());
        $payload = $this->json($response);
        self::assertSame('validation_failed', $payload['error'] ?? null);
        self::assertIsArray($payload['details'] ?? null);
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature;

use BudgetBook\Bootstrap\AppFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversNothing]
final class HealthCheckTest extends TestCase
{
    public function test_get_api_health_returns_200_with_ok_payload(): void
    {
        $app = AppFactory::create();

        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/health');
        $response = $app->handle($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));

        $payload = json_decode((string) $response->getBody(), true);
        self::assertIsArray($payload);
        self::assertSame('ok', $payload['status'] ?? null);
        self::assertArrayHasKey('time', $payload);
    }
}

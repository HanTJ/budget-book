<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Feature;

use BudgetBook\Bootstrap\AppFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversNothing]
final class CorsBehaviorTest extends TestCase
{
    /** @var string|null */
    private ?string $prev;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prev = $_ENV['CORS_ALLOWED_ORIGIN'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->prev === null) {
            unset($_ENV['CORS_ALLOWED_ORIGIN']);
        } else {
            $_ENV['CORS_ALLOWED_ORIGIN'] = $this->prev;
        }
        parent::tearDown();
    }

    public function test_cors_header_present_when_origin_configured(): void
    {
        $_ENV['CORS_ALLOWED_ORIGIN'] = 'http://localhost:3000';

        $app = AppFactory::create();
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        self::assertSame('http://localhost:3000', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_cors_header_absent_when_same_origin(): void
    {
        $_ENV['CORS_ALLOWED_ORIGIN'] = 'same-origin';

        $app = AppFactory::create();
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }

    public function test_cors_header_absent_when_empty(): void
    {
        $_ENV['CORS_ALLOWED_ORIGIN'] = '';

        $app = AppFactory::create();
        $response = $app->handle((new ServerRequestFactory())->createServerRequest('GET', '/api/health'));

        self::assertSame('', $response->getHeaderLine('Access-Control-Allow-Origin'));
    }
}

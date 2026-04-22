<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Interface\Http\Middleware;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Interface\Http\Middleware\AdminAuthMiddleware;
use BudgetBook\Interface\Http\Middleware\JwtAuthMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(AdminAuthMiddleware::class)]
final class AdminAuthMiddlewareTest extends TestCase
{
    public function test_allows_admin_through(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public bool $invoked = false;
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->invoked = true;
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $mw = new AdminAuthMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/admin/users')
            ->withAttribute(
                JwtAuthMiddleware::CLAIMS_ATTR,
                new TokenClaims(userId: 1, role: UserRole::ADMIN, type: 'access'),
            );

        $response = $mw->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertTrue($handler->invoked);
    }

    public function test_rejects_non_admin_with_403(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('handler must not be called');
            }
        };

        $mw = new AdminAuthMiddleware();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/admin/users')
            ->withAttribute(
                JwtAuthMiddleware::CLAIMS_ATTR,
                new TokenClaims(userId: 2, role: UserRole::USER, type: 'access'),
            );

        $response = $mw->process($request, $handler);

        self::assertSame(403, $response->getStatusCode());
    }

    public function test_rejects_missing_claims_with_401(): void
    {
        $handler = new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('handler must not be called');
            }
        };

        $mw = new AdminAuthMiddleware();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/admin/users');

        $response = $mw->process($request, $handler);

        self::assertSame(401, $response->getStatusCode());
    }
}

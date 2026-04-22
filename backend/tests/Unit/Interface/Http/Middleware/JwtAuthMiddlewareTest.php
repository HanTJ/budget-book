<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Interface\Http\Middleware;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Infrastructure\Security\FakeTokenService;
use BudgetBook\Interface\Http\Middleware\JwtAuthMiddleware;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

#[CoversClass(JwtAuthMiddleware::class)]
final class JwtAuthMiddlewareTest extends TestCase
{
    public function test_attaches_claims_when_bearer_token_valid(): void
    {
        $mw = new JwtAuthMiddleware(new FakeTokenService());

        /** @var TokenClaims|null $captured */
        $captured = null;
        $handler = new class ($captured) implements RequestHandlerInterface {
            /** @var TokenClaims|null */
            public ?TokenClaims $seen = null;

            public function __construct(?TokenClaims &$captured)
            {
                $captured = &$this->seen;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $attr = $request->getAttribute('token_claims');
                $this->seen = $attr instanceof TokenClaims ? $attr : null;
                return (new ResponseFactory())->createResponse(200);
            }
        };

        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/me')
            ->withHeader('Authorization', 'Bearer access-42-USER');

        $response = $mw->process($request, $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($handler->seen);
        self::assertSame(42, $handler->seen->userId);
        self::assertSame(UserRole::USER, $handler->seen->role);
    }

    public function test_returns_401_when_header_missing(): void
    {
        $mw = new JwtAuthMiddleware(new FakeTokenService());
        $handler = $this->failingHandler();
        $request = (new ServerRequestFactory())->createServerRequest('GET', '/api/me');

        $response = $mw->process($request, $handler);

        self::assertSame(401, $response->getStatusCode());
    }

    public function test_returns_401_when_token_invalid(): void
    {
        $mw = new JwtAuthMiddleware(new FakeTokenService());
        $handler = $this->failingHandler();
        $request = (new ServerRequestFactory())
            ->createServerRequest('GET', '/api/me')
            ->withHeader('Authorization', 'Bearer not-a-valid-token');

        $response = $mw->process($request, $handler);

        self::assertSame(401, $response->getStatusCode());
    }

    private function failingHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \RuntimeException('handler should not be called');
            }
        };
    }
}

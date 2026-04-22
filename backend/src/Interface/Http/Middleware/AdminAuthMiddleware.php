<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Middleware;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Interface\Http\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class AdminAuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $claims = $request->getAttribute(JwtAuthMiddleware::CLAIMS_ATTR);
        if (!$claims instanceof TokenClaims) {
            return JsonResponder::error(
                (new ResponseFactory())->createResponse(401),
                401,
                'missing_token',
            );
        }

        if ($claims->role !== UserRole::ADMIN) {
            return JsonResponder::error(
                (new ResponseFactory())->createResponse(403),
                403,
                'admin_required',
            );
        }

        return $handler->handle($request);
    }
}

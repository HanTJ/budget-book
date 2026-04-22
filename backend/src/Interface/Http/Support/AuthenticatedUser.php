<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Support;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Interface\Http\Middleware\JwtAuthMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class AuthenticatedUser
{
    public static function require(ServerRequestInterface $request): TokenClaims
    {
        $claims = $request->getAttribute(JwtAuthMiddleware::CLAIMS_ATTR);
        if (!$claims instanceof TokenClaims) {
            throw new RuntimeException('JWT middleware did not attach claims.');
        }
        return $claims;
    }
}

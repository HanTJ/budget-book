<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Middleware;

use BudgetBook\Application\Auth\TokenService;
use BudgetBook\Application\Exception\InvalidToken;
use BudgetBook\Interface\Http\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ResponseFactory;

final class JwtAuthMiddleware implements MiddlewareInterface
{
    public const CLAIMS_ATTR = 'token_claims';

    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if ($header === '' || !str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized('missing_token');
        }

        $token = substr($header, 7);

        try {
            $claims = $this->tokens->verifyAccess($token);
        } catch (InvalidToken) {
            return $this->unauthorized('invalid_token');
        }

        return $handler->handle($request->withAttribute(self::CLAIMS_ATTR, $claims));
    }

    private function unauthorized(string $code): ResponseInterface
    {
        $response = (new ResponseFactory())->createResponse(401);
        return JsonResponder::error($response, 401, $code);
    }
}

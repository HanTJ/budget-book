<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers;

use BudgetBook\Application\Auth\TokenClaims;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Interface\Http\Middleware\JwtAuthMiddleware;
use BudgetBook\Interface\Http\Support\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class MeController
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = $request->getAttribute(JwtAuthMiddleware::CLAIMS_ATTR);
        if (!$claims instanceof TokenClaims) {
            throw new RuntimeException('JWT middleware did not attach claims.');
        }

        $user = $this->users->findById($claims->userId);
        if ($user === null) {
            return JsonResponder::error($response, 404, 'user_not_found');
        }

        return JsonResponder::json($response, 200, [
            'id' => $user->id(),
            'email' => $user->email->value,
            'display_name' => $user->displayName,
            'role' => $user->role->value,
            'status' => $user->status->value,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Auth;

use BudgetBook\Application\Auth\LoginUser;
use BudgetBook\Application\Auth\LoginUserInput;
use BudgetBook\Application\Exception\AccountPending;
use BudgetBook\Application\Exception\AccountSuspended;
use BudgetBook\Application\Exception\InvalidCredentials;
use BudgetBook\Interface\Http\Support\JsonResponder;
use BudgetBook\Interface\Http\Validation\LoginValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class LoginController
{
    public function __construct(
        private readonly LoginUser $useCase,
        private readonly LoginValidator $validator,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $request->getParsedBody();
        $errors = $this->validator->validate($payload);

        if ($errors !== []) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => $errors]);
        }

        /** @var array{email: string, password: string} $payload */
        try {
            $output = $this->useCase->handle(new LoginUserInput(
                email: $payload['email'],
                plainPassword: $payload['password'],
            ));
        } catch (InvalidCredentials) {
            return JsonResponder::error($response, 401, 'invalid_credentials');
        } catch (AccountPending) {
            return JsonResponder::error($response, 403, 'account_pending');
        } catch (AccountSuspended) {
            return JsonResponder::error($response, 403, 'account_suspended');
        }

        return JsonResponder::json($response, 200, [
            'access_token' => $output->accessToken,
            'refresh_token' => $output->refreshToken,
            'token_type' => 'Bearer',
        ]);
    }
}

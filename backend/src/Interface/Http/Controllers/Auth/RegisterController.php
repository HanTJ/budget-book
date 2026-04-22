<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Auth;

use BudgetBook\Application\Auth\RegisterUser;
use BudgetBook\Application\Auth\RegisterUserInput;
use BudgetBook\Application\Exception\EmailAlreadyRegistered;
use BudgetBook\Interface\Http\Support\JsonResponder;
use BudgetBook\Interface\Http\Validation\RegisterValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class RegisterController
{
    public function __construct(
        private readonly RegisterUser $useCase,
        private readonly RegisterValidator $validator,
    ) {
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $payload = $request->getParsedBody();
        $errors = $this->validator->validate($payload);

        if ($errors !== []) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => $errors]);
        }

        /** @var array{email: string, password: string, display_name: string} $payload */
        try {
            $output = $this->useCase->handle(new RegisterUserInput(
                email: $payload['email'],
                plainPassword: $payload['password'],
                displayName: $payload['display_name'],
            ));
        } catch (EmailAlreadyRegistered) {
            return JsonResponder::error($response, 409, 'email_already_registered');
        }

        return JsonResponder::json($response, 201, [
            'id' => $output->userId,
            'email' => $output->email,
            'status' => $output->status->value,
        ]);
    }
}

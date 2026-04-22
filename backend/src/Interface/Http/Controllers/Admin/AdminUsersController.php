<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Admin;

use BudgetBook\Application\Admin\ListUsers;
use BudgetBook\Application\Admin\ListUsersInput;
use BudgetBook\Application\Admin\SoftDeleteUser;
use BudgetBook\Application\Admin\UpdateUser;
use BudgetBook\Application\Admin\UpdateUserInput;
use BudgetBook\Application\Exception\UserNotFound;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Interface\Http\Support\JsonResponder;
use BudgetBook\Interface\Http\Support\UserPresenter;
use DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AdminUsersController
{
    public function __construct(
        private readonly ListUsers $list,
        private readonly UpdateUser $update,
        private readonly SoftDeleteUser $delete,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $statusRaw = $request->getQueryParams()['status'] ?? null;
        $status = null;
        if (is_string($statusRaw) && $statusRaw !== '') {
            $status = UserStatus::tryFrom($statusRaw);
            if ($status === null) {
                return JsonResponder::error($response, 422, 'validation_failed', [
                    'details' => ['status' => 'status must be one of PENDING/ACTIVE/SUSPENDED.'],
                ]);
            }
        }

        $users = $this->list->handle(new ListUsersInput(status: $status));

        return JsonResponder::json($response, 200, [
            'users' => array_map(UserPresenter::toArray(...), $users),
        ]);
    }

    /**
     * @param array<string, string> $args
     */
    public function patch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        $payload = $request->getParsedBody();
        if (!is_array($payload)) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['_root' => 'body must be JSON object.'],
            ]);
        }

        $status = null;
        if (isset($payload['status'])) {
            if (!is_string($payload['status']) || UserStatus::tryFrom($payload['status']) === null) {
                return JsonResponder::error($response, 422, 'validation_failed', [
                    'details' => ['status' => 'invalid status.'],
                ]);
            }
            $status = UserStatus::from($payload['status']);
        }

        $role = null;
        if (isset($payload['role'])) {
            if (!is_string($payload['role']) || UserRole::tryFrom($payload['role']) === null) {
                return JsonResponder::error($response, 422, 'validation_failed', [
                    'details' => ['role' => 'invalid role.'],
                ]);
            }
            $role = UserRole::from($payload['role']);
        }

        try {
            $user = $this->update->handle(new UpdateUserInput(
                userId: $id,
                status: $status,
                role: $role,
            ));
        } catch (UserNotFound) {
            return JsonResponder::error($response, 404, 'user_not_found');
        } catch (DomainException $e) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['_root' => $e->getMessage()],
            ]);
        }

        return JsonResponder::json($response, 200, UserPresenter::toArray($user));
    }

    /**
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        try {
            $this->delete->handle($id);
        } catch (UserNotFound) {
            return JsonResponder::error($response, 404, 'user_not_found');
        }
        return $response->withStatus(204);
    }
}

<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Ledger;

use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Application\Ledger\CreateAccount;
use BudgetBook\Application\Ledger\DeleteAccount;
use BudgetBook\Application\Ledger\RenameAccount;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Domain\Ledger\AccountType;
use BudgetBook\Domain\Ledger\CashFlowSection;
use BudgetBook\Interface\Http\Support\AccountPresenter;
use BudgetBook\Interface\Http\Support\AuthenticatedUser;
use BudgetBook\Interface\Http\Support\JsonResponder;
use BudgetBook\Interface\Http\Validation\AccountValidator;
use DomainException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class AccountController
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly CreateAccount $create,
        private readonly RenameAccount $rename,
        private readonly DeleteAccount $delete,
        private readonly AccountValidator $validator,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $accounts = $this->accounts->listForUser($claims->userId);
        return JsonResponder::json($response, 200, [
            'accounts' => array_map(AccountPresenter::toArray(...), $accounts),
        ]);
    }

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $payload = $request->getParsedBody();
        $errors = $this->validator->validateCreate($payload);
        if ($errors !== []) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => $errors]);
        }

        /** @var array{name:string, account_type:string, subtype?:string|null, cash_flow_section?:string, opening_balance?:string} $payload */
        try {
            $account = $this->create->handle(
                userId: $claims->userId,
                name: $payload['name'],
                type: AccountType::from($payload['account_type']),
                subtype: $payload['subtype'] ?? null,
                section: CashFlowSection::from($payload['cash_flow_section'] ?? CashFlowSection::NONE->value),
                openingBalance: isset($payload['opening_balance']) ? (string) $payload['opening_balance'] : null,
            );
        } catch (DomainException $e) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => ['_root' => $e->getMessage()]]);
        }

        return JsonResponder::json($response, 201, AccountPresenter::toArray($account));
    }

    /**
     * @param array<string, string> $args
     */
    public function patch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $id = (int) ($args['id'] ?? 0);
        $payload = $request->getParsedBody();
        $errors = $this->validator->validateRename($payload);
        if ($errors !== []) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => $errors]);
        }

        /** @var array{name:string} $payload */
        try {
            $account = $this->rename->handle($claims->userId, $id, $payload['name']);
        } catch (AccountNotFound) {
            return JsonResponder::error($response, 404, 'account_not_found');
        }

        return JsonResponder::json($response, 200, AccountPresenter::toArray($account));
    }

    /**
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $id = (int) ($args['id'] ?? 0);

        try {
            $this->delete->handle($claims->userId, $id);
        } catch (AccountNotFound) {
            return JsonResponder::error($response, 404, 'account_not_found');
        }

        return $response->withStatus(204);
    }
}

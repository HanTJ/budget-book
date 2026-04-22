<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Ledger;

use BudgetBook\Application\Exception\AccountNotFound;
use BudgetBook\Application\Exception\InvalidJournalEntry;
use BudgetBook\Application\Ledger\RecordJournalEntry;
use BudgetBook\Application\Ledger\RecordJournalEntryInput;
use BudgetBook\Application\Ledger\UpdateJournalEntry;
use BudgetBook\Domain\Ledger\JournalEntryRepository;
use BudgetBook\Domain\Ledger\PaymentMethod;
use BudgetBook\Interface\Http\Support\AuthenticatedUser;
use BudgetBook\Interface\Http\Support\JournalEntryPresenter;
use BudgetBook\Interface\Http\Support\JsonResponder;
use BudgetBook\Interface\Http\Validation\JournalEntryValidator;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class JournalEntryController
{
    public function __construct(
        private readonly RecordJournalEntry $record,
        private readonly UpdateJournalEntry $update,
        private readonly JournalEntryRepository $entries,
        private readonly JournalEntryValidator $validator,
    ) {
    }

    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $params = $request->getQueryParams();

        try {
            $from = new DateTimeImmutable((string) ($params['from'] ?? 'first day of this month'));
            $to = new DateTimeImmutable((string) ($params['to'] ?? 'last day of this month'));
        } catch (Throwable) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['range' => 'from/to must be valid dates.'],
            ]);
        }

        $list = $this->entries->listForUser($claims->userId, $from, $to);
        return JsonResponder::json($response, 200, [
            'entries' => array_map(JournalEntryPresenter::toArray(...), $list),
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

        /** @var array{
         *     occurred_on:string,
         *     amount:mixed,
         *     payment_method:string,
         *     category_account_id:int,
         *     counter_account_id?:int|null,
         *     merchant?:string|null,
         *     memo?:string|null,
         * } $payload
         */
        try {
            $entry = $this->record->handle(new RecordJournalEntryInput(
                userId: $claims->userId,
                occurredOn: $payload['occurred_on'],
                amount: (string) $payload['amount'],
                paymentMethod: PaymentMethod::from($payload['payment_method']),
                categoryAccountId: $payload['category_account_id'],
                counterAccountId: $payload['counter_account_id'] ?? null,
                merchant: $payload['merchant'] ?? null,
                memo: $payload['memo'] ?? null,
            ));
        } catch (AccountNotFound) {
            return JsonResponder::error($response, 404, 'account_not_found');
        } catch (InvalidJournalEntry $e) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['_root' => $e->getMessage()],
            ]);
        }

        return JsonResponder::json($response, 201, JournalEntryPresenter::toArray($entry));
    }

    /**
     * @param array<string, string> $args
     */
    public function patch(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $id = (int) ($args['id'] ?? 0);
        $payload = $request->getParsedBody();
        $errors = $this->validator->validateCreate($payload);
        if ($errors !== []) {
            return JsonResponder::error($response, 422, 'validation_failed', ['details' => $errors]);
        }

        if ($this->entries->findById($id, $claims->userId) === null) {
            return JsonResponder::error($response, 404, 'entry_not_found');
        }

        /** @var array{
         *     occurred_on:string,
         *     amount:mixed,
         *     payment_method:string,
         *     category_account_id:int,
         *     counter_account_id?:int|null,
         *     merchant?:string|null,
         *     memo?:string|null,
         * } $payload
         */
        try {
            $entry = $this->update->handle(
                $claims->userId,
                $id,
                new RecordJournalEntryInput(
                    userId: $claims->userId,
                    occurredOn: $payload['occurred_on'],
                    amount: (string) $payload['amount'],
                    paymentMethod: PaymentMethod::from($payload['payment_method']),
                    categoryAccountId: $payload['category_account_id'],
                    counterAccountId: $payload['counter_account_id'] ?? null,
                    merchant: $payload['merchant'] ?? null,
                    memo: $payload['memo'] ?? null,
                ),
            );
        } catch (AccountNotFound) {
            return JsonResponder::error($response, 404, 'account_not_found');
        } catch (InvalidJournalEntry $e) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['_root' => $e->getMessage()],
            ]);
        }

        return JsonResponder::json($response, 200, JournalEntryPresenter::toArray($entry));
    }

    /**
     * @param array<string, string> $args
     */
    public function destroy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $id = (int) ($args['id'] ?? 0);

        $entry = $this->entries->findById($id, $claims->userId);
        if ($entry === null) {
            return JsonResponder::error($response, 404, 'entry_not_found');
        }

        $this->entries->softDelete($id, $claims->userId);
        return $response->withStatus(204);
    }
}

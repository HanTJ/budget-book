<?php

declare(strict_types=1);

namespace BudgetBook\Interface\Http\Controllers\Reporting;

use BudgetBook\Domain\Reporting\BalanceSheetService;
use BudgetBook\Domain\Reporting\CashFlowStatementService;
use BudgetBook\Domain\Reporting\DailyReportService;
use BudgetBook\Interface\Http\Support\AuthenticatedUser;
use BudgetBook\Interface\Http\Support\BalanceSheetPresenter;
use BudgetBook\Interface\Http\Support\CashFlowStatementPresenter;
use BudgetBook\Interface\Http\Support\JournalEntryPresenter;
use BudgetBook\Interface\Http\Support\JsonResponder;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

final class ReportsController
{
    public function __construct(
        private readonly BalanceSheetService $balanceSheet,
        private readonly CashFlowStatementService $cashFlow,
        private readonly DailyReportService $daily,
    ) {
    }

    public function balanceSheet(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $on = $this->parseDate($request->getQueryParams()['on'] ?? null);
        if ($on === null) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['on' => 'on query parameter must be YYYY-MM-DD.'],
            ]);
        }

        $sheet = $this->balanceSheet->compute($claims->userId, $on);
        return JsonResponder::json($response, 200, BalanceSheetPresenter::toArray($sheet));
    }

    public function cashFlow(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $params = $request->getQueryParams();
        $from = $this->parseDate($params['from'] ?? null);
        $to = $this->parseDate($params['to'] ?? null);

        if ($from === null || $to === null) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['range' => 'from/to query parameters must be YYYY-MM-DD.'],
            ]);
        }

        $stmt = $this->cashFlow->compute($claims->userId, $from, $to);
        return JsonResponder::json($response, 200, CashFlowStatementPresenter::toArray($stmt));
    }

    public function daily(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $claims = AuthenticatedUser::require($request);
        $date = $this->parseDate($request->getQueryParams()['date'] ?? null);
        if ($date === null) {
            return JsonResponder::error($response, 422, 'validation_failed', [
                'details' => ['date' => 'date query parameter must be YYYY-MM-DD.'],
            ]);
        }

        $report = $this->daily->compute($claims->userId, $date);
        return JsonResponder::json($response, 200, [
            'date' => $date->format('Y-m-d'),
            'balance_sheet' => BalanceSheetPresenter::toArray($report->balanceSheet),
            'cash_flow' => CashFlowStatementPresenter::toArray($report->cashFlowForDay),
            'entries' => array_map(JournalEntryPresenter::toArray(...), $report->dayEntries),
        ]);
    }

    private function parseDate(mixed $raw): ?DateTimeImmutable
    {
        if (!is_string($raw) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) !== 1) {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }
}

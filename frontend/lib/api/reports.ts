import { apiRequest } from './client';
import {
  type BalanceSheet,
  balanceSheetSchema,
  type CashFlowStatement,
  cashFlowStatementSchema,
  type DailyReport,
  dailyReportSchema,
} from '../schemas/reports';

export async function fetchBalanceSheet(token: string, on: string): Promise<BalanceSheet> {
  const raw = await apiRequest<unknown>(`/reports/balance-sheet?on=${encodeURIComponent(on)}`, {
    token,
  });
  return balanceSheetSchema.parse(raw);
}

export async function fetchCashFlow(
  token: string,
  from: string,
  to: string,
): Promise<CashFlowStatement> {
  const raw = await apiRequest<unknown>(
    `/reports/cash-flow?from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
    { token },
  );
  return cashFlowStatementSchema.parse(raw);
}

export async function fetchDailyReport(token: string, date: string): Promise<DailyReport> {
  const raw = await apiRequest<unknown>(`/reports/daily?date=${encodeURIComponent(date)}`, {
    token,
  });
  return dailyReportSchema.parse(raw);
}

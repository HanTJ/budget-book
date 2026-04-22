import { z } from 'zod';
import { journalEntrySchema } from './entries';

export const balanceSheetLineSchema = z.object({
  account_id: z.number(),
  name: z.string(),
  account_type: z.enum(['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE']),
  subtype: z.string().nullable(),
  balance: z.string(),
});
export type BalanceSheetLine = z.infer<typeof balanceSheetLineSchema>;

export const balanceSheetSchema = z.object({
  as_of: z.string(),
  total_assets: z.string(),
  total_liabilities: z.string(),
  total_equity: z.string(),
  net_income: z.string(),
  is_balanced: z.boolean(),
  assets: z.array(balanceSheetLineSchema),
  liabilities: z.array(balanceSheetLineSchema),
  equity: z.array(balanceSheetLineSchema),
});
export type BalanceSheet = z.infer<typeof balanceSheetSchema>;

const cashFlowSectionSchema = z.object({
  inflow: z.string(),
  outflow: z.string(),
  net: z.string(),
});

export const cashFlowStatementSchema = z.object({
  from: z.string(),
  to: z.string(),
  opening_cash_balance: z.string(),
  closing_cash_balance: z.string(),
  is_reconciled: z.boolean(),
  operating: cashFlowSectionSchema,
  investing: cashFlowSectionSchema,
  financing: cashFlowSectionSchema,
  net_change: z.string(),
});
export type CashFlowStatement = z.infer<typeof cashFlowStatementSchema>;

export const dailyReportSchema = z.object({
  date: z.string(),
  balance_sheet: balanceSheetSchema,
  cash_flow: cashFlowStatementSchema,
  entries: z.array(journalEntrySchema),
});
export type DailyReport = z.infer<typeof dailyReportSchema>;

import { z } from 'zod';

export const accountTypeSchema = z.enum(['ASSET', 'LIABILITY', 'EQUITY', 'INCOME', 'EXPENSE']);
export type AccountType = z.infer<typeof accountTypeSchema>;

export const cashFlowSectionSchema = z.enum(['OPERATING', 'INVESTING', 'FINANCING', 'NONE']);
export type CashFlowSection = z.infer<typeof cashFlowSectionSchema>;

export const accountSchema = z.object({
  id: z.number(),
  user_id: z.number(),
  name: z.string(),
  account_type: accountTypeSchema,
  subtype: z.string().nullable(),
  cash_flow_section: cashFlowSectionSchema,
  normal_balance: z.enum(['DEBIT', 'CREDIT']),
  opening_balance: z.string(),
  is_system: z.boolean(),
});
export type Account = z.infer<typeof accountSchema>;

export const accountListSchema = z.object({
  accounts: z.array(accountSchema),
});

export const createAccountSchema = z
  .object({
    name: z
      .string()
      .trim()
      .min(1, '이름을 입력해주세요.')
      .max(100, '이름은 100자 이하여야 합니다.'),
    account_type: accountTypeSchema,
    subtype: z.string().optional(),
    cash_flow_section: cashFlowSectionSchema.default('NONE'),
    opening_balance: z
      .string()
      .regex(/^\d+(\.\d{1,2})?$/, '양수 숫자를 입력해주세요 (예: 10000.00).')
      .optional()
      .or(z.literal('')),
  })
  .refine(
    (data) =>
      !(data.account_type === 'INCOME' || data.account_type === 'EXPENSE') ||
      data.cash_flow_section !== 'NONE',
    { message: '수익/비용 계정은 현금흐름 섹션이 필요합니다.', path: ['cash_flow_section'] },
  );

export type CreateAccountInput = z.infer<typeof createAccountSchema>;

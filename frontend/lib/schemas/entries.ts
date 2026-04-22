import { z } from 'zod';

export const paymentMethodSchema = z.enum(['CASH', 'CARD', 'TRANSFER']);
export type PaymentMethod = z.infer<typeof paymentMethodSchema>;

export const journalEntryLineSchema = z.object({
  account_id: z.number(),
  debit: z.string(),
  credit: z.string(),
  line_no: z.number(),
});

export const journalEntrySchema = z.object({
  id: z.number(),
  user_id: z.number(),
  occurred_on: z.string(),
  memo: z.string().nullable(),
  merchant: z.string().nullable(),
  payment_method: paymentMethodSchema.nullable(),
  source: z.string(),
  amount: z.string(),
  lines: z.array(journalEntryLineSchema),
});
export type JournalEntry = z.infer<typeof journalEntrySchema>;

export const journalEntryListSchema = z.object({
  entries: z.array(journalEntrySchema),
});

export const recordEntrySchema = z.object({
  occurred_on: z
    .string()
    .regex(/^\d{4}-\d{2}-\d{2}$/, '날짜는 YYYY-MM-DD 형식이어야 합니다.'),
  amount: z
    .string()
    .regex(/^\d+(\.\d{1,2})?$/, '양수 금액(소수 최대 2자리)을 입력해주세요.')
    .refine((v) => Number(v) > 0, '금액은 0보다 커야 합니다.'),
  payment_method: paymentMethodSchema,
  category_account_id: z.number().int().positive('카테고리 계정을 선택해주세요.'),
  counter_account_id: z.number().int().positive().optional(),
  merchant: z.string().max(200).optional(),
  memo: z.string().max(255).optional(),
});
export type RecordEntryInput = z.infer<typeof recordEntrySchema>;

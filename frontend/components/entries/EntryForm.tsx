'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import type { Account } from '@/lib/schemas/accounts';
import { type RecordEntryInput, recordEntrySchema } from '@/lib/schemas/entries';

interface Props {
  accounts: Account[];
  onSubmit: (input: RecordEntryInput) => Promise<void>;
}

const formSchema = recordEntrySchema.superRefine((data, ctx) => {
  if (data.payment_method === 'TRANSFER' && data.counter_account_id === undefined) {
    ctx.addIssue({
      path: ['counter_account_id'],
      code: z.ZodIssueCode.custom,
      message: '이체 거래는 상대 계정을 선택해야 합니다.',
    });
  }
});

type FormValues = z.infer<typeof formSchema>;

export function EntryForm({ accounts, onSubmit }: Props) {
  const categoryAccounts = accounts.filter(
    (a) => a.account_type === 'EXPENSE' || a.account_type === 'INCOME',
  );
  const counterAccounts = accounts.filter(
    (a) => a.account_type === 'ASSET' || a.account_type === 'LIABILITY',
  );

  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: {
      occurred_on: new Date().toISOString().slice(0, 10),
      amount: '',
      payment_method: 'CASH',
      category_account_id: 0,
      counter_account_id: undefined,
      merchant: '',
      memo: '',
    },
  });

  const paymentMethod = form.watch('payment_method');

  const handle = form.handleSubmit(async (values) => {
    const payload: RecordEntryInput = {
      occurred_on: values.occurred_on,
      amount: values.amount,
      payment_method: values.payment_method,
      category_account_id: values.category_account_id,
      ...(values.counter_account_id ? { counter_account_id: values.counter_account_id } : {}),
      ...(values.merchant ? { merchant: values.merchant } : {}),
      ...(values.memo ? { memo: values.memo } : {}),
    };
    await onSubmit(payload);
    form.reset({ ...form.getValues(), amount: '', merchant: '', memo: '' });
  });

  return (
    <form onSubmit={handle} className="flex flex-col gap-3 rounded border p-4" noValidate>
      <h2 className="text-lg font-semibold">거래 기록</h2>

      <label className="flex flex-col gap-1">
        <span>날짜</span>
        <input type="date" className="rounded border p-2" {...form.register('occurred_on')} />
        {form.formState.errors.occurred_on && (
          <span className="text-sm text-red-600">{form.formState.errors.occurred_on.message}</span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>금액</span>
        <input
          type="text"
          inputMode="decimal"
          className="rounded border p-2"
          {...form.register('amount')}
        />
        {form.formState.errors.amount && (
          <span className="text-sm text-red-600">{form.formState.errors.amount.message}</span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>지불방식</span>
        <select className="rounded border p-2" {...form.register('payment_method')}>
          <option value="CASH">현금</option>
          <option value="CARD">카드</option>
          <option value="TRANSFER">계좌이체</option>
        </select>
      </label>

      <label className="flex flex-col gap-1">
        <span>카테고리</span>
        <select
          className="rounded border p-2"
          {...form.register('category_account_id', { valueAsNumber: true })}
        >
          <option value={0}>선택하세요</option>
          {categoryAccounts.map((a) => (
            <option key={a.id} value={a.id}>
              [{a.account_type === 'EXPENSE' ? '비용' : '수익'}] {a.name}
            </option>
          ))}
        </select>
        {form.formState.errors.category_account_id && (
          <span className="text-sm text-red-600">
            {form.formState.errors.category_account_id.message}
          </span>
        )}
      </label>

      {paymentMethod === 'TRANSFER' && (
        <label className="flex flex-col gap-1">
          <span>상대 계정 (자산/부채)</span>
          <select
            className="rounded border p-2"
            {...form.register('counter_account_id', {
              setValueAs: (v) => (v === '' || v === '0' ? undefined : Number(v)),
            })}
          >
            <option value="">선택하세요</option>
            {counterAccounts.map((a) => (
              <option key={a.id} value={a.id}>
                [{a.account_type === 'ASSET' ? '자산' : '부채'}] {a.name}
              </option>
            ))}
          </select>
          {form.formState.errors.counter_account_id && (
            <span className="text-sm text-red-600">
              {form.formState.errors.counter_account_id.message}
            </span>
          )}
        </label>
      )}

      <label className="flex flex-col gap-1">
        <span>사용처</span>
        <input type="text" className="rounded border p-2" {...form.register('merchant')} />
      </label>

      <label className="flex flex-col gap-1">
        <span>메모</span>
        <input type="text" className="rounded border p-2" {...form.register('memo')} />
      </label>

      <button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="self-end rounded bg-black px-4 py-2 text-white disabled:opacity-50"
      >
        기록
      </button>
    </form>
  );
}

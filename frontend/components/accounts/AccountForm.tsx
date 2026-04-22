'use client';

import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import {
  type AccountType,
  type CreateAccountInput,
  createAccountSchema,
} from '@/lib/schemas/accounts';

interface Props {
  onCreate: (payload: CreateAccountInput) => Promise<void>;
}

const TYPE_OPTIONS: Array<{ value: AccountType; label: string }> = [
  { value: 'ASSET', label: '자산 (ASSET)' },
  { value: 'LIABILITY', label: '부채 (LIABILITY)' },
  { value: 'EQUITY', label: '자본 (EQUITY)' },
  { value: 'INCOME', label: '수익 (INCOME)' },
  { value: 'EXPENSE', label: '비용 (EXPENSE)' },
];

export function AccountForm({ onCreate }: Props) {
  const form = useForm<CreateAccountInput>({
    resolver: zodResolver(createAccountSchema),
    defaultValues: {
      name: '',
      account_type: 'ASSET',
      subtype: '',
      cash_flow_section: 'NONE',
      opening_balance: '',
    },
  });

  const handle = form.handleSubmit(async (values) => {
    await onCreate(values);
    form.reset();
  });

  return (
    <form onSubmit={handle} className="flex flex-col gap-3 rounded border p-4" noValidate>
      <h2 className="text-lg font-semibold">새 계정 추가</h2>

      <label className="flex flex-col gap-1">
        <span>이름</span>
        <input type="text" className="rounded border p-2" {...form.register('name')} />
        {form.formState.errors.name && (
          <span className="text-sm text-red-600">{form.formState.errors.name.message}</span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>계정과목</span>
        <select className="rounded border p-2" {...form.register('account_type')}>
          {TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </label>

      <label className="flex flex-col gap-1">
        <span>세부유형 (선택)</span>
        <input
          type="text"
          placeholder="CASH / BANK / CARD / LOAN / INVESTMENT"
          className="rounded border p-2"
          {...form.register('subtype')}
        />
      </label>

      <label className="flex flex-col gap-1">
        <span>현금흐름 섹션</span>
        <select className="rounded border p-2" {...form.register('cash_flow_section')}>
          <option value="NONE">NONE (자산/부채/자본)</option>
          <option value="OPERATING">영업 (OPERATING)</option>
          <option value="INVESTING">투자 (INVESTING)</option>
          <option value="FINANCING">재무 (FINANCING)</option>
        </select>
        {form.formState.errors.cash_flow_section && (
          <span className="text-sm text-red-600">
            {form.formState.errors.cash_flow_section.message}
          </span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>초기 잔액 (선택)</span>
        <input
          type="text"
          placeholder="0.00"
          className="rounded border p-2"
          {...form.register('opening_balance')}
        />
        {form.formState.errors.opening_balance && (
          <span className="text-sm text-red-600">
            {form.formState.errors.opening_balance.message}
          </span>
        )}
      </label>

      <button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="self-end rounded bg-black px-4 py-2 text-white disabled:opacity-50"
      >
        추가
      </button>
    </form>
  );
}

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { EntryForm } from '@/components/entries/EntryForm';
import type { Account } from '@/lib/schemas/accounts';

const accounts: Account[] = [
  {
    id: 1,
    user_id: 1,
    name: '식비',
    account_type: 'EXPENSE',
    subtype: null,
    cash_flow_section: 'OPERATING',
    normal_balance: 'DEBIT',
    opening_balance: '0.00',
    is_system: true,
  },
  {
    id: 2,
    user_id: 1,
    name: '급여',
    account_type: 'INCOME',
    subtype: null,
    cash_flow_section: 'OPERATING',
    normal_balance: 'CREDIT',
    opening_balance: '0.00',
    is_system: true,
  },
  {
    id: 10,
    user_id: 1,
    name: '은행',
    account_type: 'ASSET',
    subtype: 'BANK',
    cash_flow_section: 'NONE',
    normal_balance: 'DEBIT',
    opening_balance: '0.00',
    is_system: true,
  },
];

describe('EntryForm', () => {
  it('blocks submission with invalid amount', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<EntryForm accounts={accounts} onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText(/금액/), '-5');
    await user.click(screen.getByRole('button', { name: /기록/ }));

    expect(await screen.findByText(/양수 금액/)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('submits cash expense payload when valid', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<EntryForm accounts={accounts} onSubmit={onSubmit} />);

    const dateInput = screen.getByLabelText(/날짜/);
    await user.clear(dateInput);
    await user.type(dateInput, '2026-04-22');
    await user.type(screen.getByLabelText(/금액/), '12000');
    await user.selectOptions(screen.getByLabelText(/지불방식/), 'CASH');
    await user.selectOptions(screen.getByLabelText(/카테고리/), '1');
    await user.type(screen.getByLabelText(/사용처/), '분식집');
    await user.click(screen.getByRole('button', { name: /기록/ }));

    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        occurred_on: '2026-04-22',
        amount: '12000',
        payment_method: 'CASH',
        category_account_id: 1,
        merchant: '분식집',
      }),
    );
  });

  it('requires counter account when payment_method is TRANSFER', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<EntryForm accounts={accounts} onSubmit={onSubmit} />);

    const dateInput = screen.getByLabelText(/날짜/);
    await user.clear(dateInput);
    await user.type(dateInput, '2026-04-22');
    await user.type(screen.getByLabelText(/금액/), '70000');
    await user.selectOptions(screen.getByLabelText(/지불방식/), 'TRANSFER');
    await user.selectOptions(screen.getByLabelText(/카테고리/), '1');
    await user.click(screen.getByRole('button', { name: /기록/ }));

    expect(await screen.findByText(/이체 거래는 상대 계정/)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });
});

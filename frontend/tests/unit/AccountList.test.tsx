import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AccountList } from '@/components/accounts/AccountList';
import type { Account } from '@/lib/schemas/accounts';

const sample = (overrides: Partial<Account> = {}): Account => ({
  id: 1,
  user_id: 1,
  name: '현금',
  account_type: 'ASSET',
  subtype: 'CASH',
  cash_flow_section: 'NONE',
  normal_balance: 'DEBIT',
  opening_balance: '10000.00',
  is_system: false,
  ...overrides,
});

describe('AccountList', () => {
  it('groups accounts by account_type', () => {
    const accounts: Account[] = [
      sample({ id: 1, name: '현금', account_type: 'ASSET' }),
      sample({
        id: 2,
        name: '식비',
        account_type: 'EXPENSE',
        subtype: null,
        cash_flow_section: 'OPERATING',
        normal_balance: 'DEBIT',
      }),
    ];
    render(<AccountList accounts={accounts} onDelete={vi.fn()} />);

    expect(screen.getByText(/자산/)).toBeInTheDocument();
    expect(screen.getByText(/비용/)).toBeInTheDocument();
    expect(screen.getByText('현금')).toBeInTheDocument();
    expect(screen.getByText('식비')).toBeInTheDocument();
  });

  it('invokes onDelete when delete clicked for non-system account', async () => {
    const onDelete = vi.fn();
    const user = userEvent.setup();
    render(<AccountList accounts={[sample({ id: 9, name: '임시' })]} onDelete={onDelete} />);

    await user.click(screen.getByRole('button', { name: /삭제/ }));

    expect(onDelete).toHaveBeenCalledWith(9);
  });

  it('hides delete button for system accounts', () => {
    render(
      <AccountList accounts={[sample({ id: 10, is_system: true, name: '현금' })]} onDelete={vi.fn()} />,
    );
    expect(screen.queryByRole('button', { name: /삭제/ })).not.toBeInTheDocument();
  });
});

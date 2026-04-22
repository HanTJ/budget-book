import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { DailyEntryList } from '@/components/entries/DailyEntryList';
import type { JournalEntry } from '@/lib/schemas/entries';

const make = (over: Partial<JournalEntry>): JournalEntry => ({
  id: 1,
  user_id: 1,
  occurred_on: '2026-04-22',
  memo: null,
  merchant: '분식집',
  payment_method: 'CASH',
  source: 'USER',
  amount: '10000.00',
  lines: [
    { account_id: 1, debit: '10000.00', credit: '0.00', line_no: 0 },
    { account_id: 2, debit: '0.00', credit: '10000.00', line_no: 1 },
  ],
  ...over,
});

describe('DailyEntryList', () => {
  it('groups entries by occurred_on in reverse chronological order', () => {
    const entries: JournalEntry[] = [
      make({ id: 1, occurred_on: '2026-04-22', merchant: '분식집', amount: '12000.00' }),
      make({ id: 2, occurred_on: '2026-04-21', merchant: '마트', amount: '30000.00' }),
      make({ id: 3, occurred_on: '2026-04-22', merchant: '카페', amount: '5000.00' }),
    ];
    render(<DailyEntryList entries={entries} onDelete={vi.fn()} />);

    const headings = screen.getAllByRole('heading', { level: 3 });
    expect(headings[0]).toHaveTextContent('2026-04-22');
    expect(headings[1]).toHaveTextContent('2026-04-21');
  });

  it('renders empty state when no entries', () => {
    render(<DailyEntryList entries={[]} onDelete={vi.fn()} />);
    expect(screen.getByText(/아직 거래가 없습니다/)).toBeInTheDocument();
  });

  it('invokes onDelete with entry id', async () => {
    const onDelete = vi.fn();
    const user = userEvent.setup();
    render(
      <DailyEntryList
        entries={[make({ id: 42, merchant: '삭제 대상' })]}
        onDelete={onDelete}
      />,
    );

    await user.click(screen.getByRole('button', { name: /삭제/ }));
    expect(onDelete).toHaveBeenCalledWith(42);
  });
});

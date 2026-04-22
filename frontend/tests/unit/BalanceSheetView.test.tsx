import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { BalanceSheetView } from '@/components/reports/BalanceSheetView';
import type { BalanceSheet } from '@/lib/schemas/reports';

const sample: BalanceSheet = {
  as_of: '2026-04-22',
  total_assets: '600000.00',
  total_liabilities: '30000.00',
  total_equity: '570000.00',
  net_income: '-30000.00',
  is_balanced: true,
  assets: [
    {
      account_id: 1,
      name: '현금',
      account_type: 'ASSET',
      subtype: 'CASH',
      balance: '100000.00',
    },
    {
      account_id: 2,
      name: '은행',
      account_type: 'ASSET',
      subtype: 'BANK',
      balance: '500000.00',
    },
  ],
  liabilities: [
    {
      account_id: 3,
      name: '카드',
      account_type: 'LIABILITY',
      subtype: 'CARD',
      balance: '30000.00',
    },
  ],
  equity: [
    {
      account_id: 0,
      name: '개시자본(초기 잔액)',
      account_type: 'EQUITY',
      subtype: null,
      balance: '600000.00',
    },
  ],
};

describe('BalanceSheetView', () => {
  it('renders totals and identity flag', () => {
    render(<BalanceSheetView sheet={sample} />);

    expect(screen.getByText(/자산 합계/)).toBeInTheDocument();
    expect(screen.getAllByText('600000.00').length).toBeGreaterThan(0);
    expect(screen.getByText(/부채 합계/)).toBeInTheDocument();
    expect(screen.getAllByText('30000.00').length).toBeGreaterThan(0);
    expect(screen.getByText(/자본 합계/)).toBeInTheDocument();
    expect(screen.getByText(/항등식 성립/)).toBeInTheDocument();
  });

  it('lists individual asset/liability/equity lines', () => {
    render(<BalanceSheetView sheet={sample} />);

    expect(screen.getByText('현금')).toBeInTheDocument();
    expect(screen.getByText('은행')).toBeInTheDocument();
    expect(screen.getByText('카드')).toBeInTheDocument();
    expect(screen.getByText(/개시자본/)).toBeInTheDocument();
  });
});

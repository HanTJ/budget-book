import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CashFlowView } from '@/components/reports/CashFlowView';
import type { CashFlowStatement } from '@/lib/schemas/reports';

const sample: CashFlowStatement = {
  from: '2026-04-01',
  to: '2026-04-30',
  opening_cash_balance: '0.00',
  closing_cash_balance: '2985000.00',
  is_reconciled: true,
  operating: { inflow: '3000000.00', outflow: '15000.00', net: '2985000.00' },
  investing: { inflow: '0.00', outflow: '0.00', net: '0.00' },
  financing: { inflow: '0.00', outflow: '0.00', net: '0.00' },
  net_change: '2985000.00',
};

describe('CashFlowView', () => {
  it('renders three sections with net values', () => {
    render(<CashFlowView statement={sample} />);

    expect(screen.getByText(/영업활동/)).toBeInTheDocument();
    expect(screen.getByText(/투자활동/)).toBeInTheDocument();
    expect(screen.getByText(/재무활동/)).toBeInTheDocument();
    expect(screen.getAllByText('2985000.00').length).toBeGreaterThan(0);
  });

  it('shows reconciliation badge', () => {
    render(<CashFlowView statement={sample} />);
    expect(screen.getByText(/조정 일치/)).toBeInTheDocument();
  });
});

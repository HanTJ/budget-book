import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AccountForm } from '@/components/accounts/AccountForm';

describe('AccountForm', () => {
  it('requires name, type, and section for INCOME accounts', async () => {
    const user = userEvent.setup();
    const onCreate = vi.fn();
    render(<AccountForm onCreate={onCreate} />);

    await user.selectOptions(screen.getByLabelText(/계정과목/), 'INCOME');
    await user.click(screen.getByRole('button', { name: /추가/ }));

    expect(await screen.findByText(/이름을 입력/)).toBeInTheDocument();
    expect(onCreate).not.toHaveBeenCalled();
  });

  it('submits payload for ASSET account with default section NONE', async () => {
    const user = userEvent.setup();
    const onCreate = vi.fn().mockResolvedValue(undefined);
    render(<AccountForm onCreate={onCreate} />);

    await user.type(screen.getByLabelText(/이름/), '현금');
    await user.selectOptions(screen.getByLabelText(/계정과목/), 'ASSET');
    await user.click(screen.getByRole('button', { name: /추가/ }));

    expect(onCreate).toHaveBeenCalledWith(
      expect.objectContaining({
        name: '현금',
        account_type: 'ASSET',
        cash_flow_section: 'NONE',
      }),
    );
  });

  it('blocks INCOME account with NONE section', async () => {
    const user = userEvent.setup();
    const onCreate = vi.fn();
    render(<AccountForm onCreate={onCreate} />);

    await user.type(screen.getByLabelText(/이름/), '급여');
    await user.selectOptions(screen.getByLabelText(/계정과목/), 'INCOME');
    // Section defaults to NONE initially
    await user.click(screen.getByRole('button', { name: /추가/ }));

    expect(await screen.findByText(/수익\/비용 계정은 현금흐름 섹션이 필요/)).toBeInTheDocument();
    expect(onCreate).not.toHaveBeenCalled();
  });
});

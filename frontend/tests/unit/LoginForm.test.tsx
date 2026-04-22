import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LoginForm } from '@/components/auth/LoginForm';

describe('LoginForm', () => {
  it('blocks submit when fields are empty', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<LoginForm onSubmit={onSubmit} />);

    await user.click(screen.getByRole('button', { name: /로그인/ }));

    expect(await screen.findByText(/올바른 이메일/)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('surfaces server error message when onSubmit rejects with account_pending', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn().mockRejectedValue(new Error('account_pending'));
    render(<LoginForm onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText(/이메일/), 'user@example.com');
    await user.type(screen.getByLabelText(/비밀번호/), 'correct-horse-battery');
    await user.click(screen.getByRole('button', { name: /로그인/ }));

    expect(await screen.findByText(/승인 대기/)).toBeInTheDocument();
  });

  it('surfaces invalid_credentials as friendly message', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn().mockRejectedValue(new Error('invalid_credentials'));
    render(<LoginForm onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText(/이메일/), 'user@example.com');
    await user.type(screen.getByLabelText(/비밀번호/), 'wrong');
    await user.click(screen.getByRole('button', { name: /로그인/ }));

    expect(await screen.findByText(/이메일 또는 비밀번호/)).toBeInTheDocument();
  });
});

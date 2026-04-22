import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RegisterForm } from '@/components/auth/RegisterForm';

describe('RegisterForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('shows validation errors when submitted empty', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn();
    render(<RegisterForm onSubmit={onSubmit} />);

    await user.click(screen.getByRole('button', { name: /가입/ }));

    expect(await screen.findByText(/올바른 이메일/)).toBeInTheDocument();
    expect(screen.getByText(/비밀번호는 8자/)).toBeInTheDocument();
    expect(screen.getByText(/표시 이름을 입력/)).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('calls onSubmit with payload when valid', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<RegisterForm onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText(/이메일/), 'new@example.com');
    await user.type(screen.getByLabelText(/비밀번호/), 'correct-horse-battery');
    await user.type(screen.getByLabelText(/표시 이름/), '새 사용자');
    await user.click(screen.getByRole('button', { name: /가입/ }));

    expect(onSubmit).toHaveBeenCalledWith({
      email: 'new@example.com',
      password: 'correct-horse-battery',
      display_name: '새 사용자',
    });
  });

  it('renders pending-approval notice when registration succeeds', async () => {
    const user = userEvent.setup();
    const onSubmit = vi.fn().mockResolvedValue(undefined);
    render(<RegisterForm onSubmit={onSubmit} />);

    await user.type(screen.getByLabelText(/이메일/), 'new@example.com');
    await user.type(screen.getByLabelText(/비밀번호/), 'correct-horse-battery');
    await user.type(screen.getByLabelText(/표시 이름/), '새 사용자');
    await user.click(screen.getByRole('button', { name: /가입/ }));

    expect(await screen.findByText(/관리자 승인/)).toBeInTheDocument();
  });
});

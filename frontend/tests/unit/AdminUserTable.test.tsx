import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AdminUserTable } from '@/components/admin/AdminUserTable';
import type { AdminUser } from '@/lib/schemas/admin';

const make = (over: Partial<AdminUser>): AdminUser => ({
  id: 1,
  email: 'user@example.com',
  display_name: 'User One',
  role: 'USER',
  status: 'PENDING',
  created_at: '2026-04-22T00:00:00+00:00',
  ...over,
});

describe('AdminUserTable', () => {
  it('renders a row per user', () => {
    const users: AdminUser[] = [
      make({ id: 1, email: 'a@example.com' }),
      make({ id: 2, email: 'b@example.com', status: 'ACTIVE' }),
    ];
    render(<AdminUserTable users={users} onApprove={vi.fn()} onSuspend={vi.fn()} onDelete={vi.fn()} />);

    expect(screen.getByText('a@example.com')).toBeInTheDocument();
    expect(screen.getByText('b@example.com')).toBeInTheDocument();
  });

  it('shows approve button for PENDING users and calls onApprove', async () => {
    const onApprove = vi.fn();
    const user = userEvent.setup();
    render(
      <AdminUserTable
        users={[make({ id: 7, status: 'PENDING' })]}
        onApprove={onApprove}
        onSuspend={vi.fn()}
        onDelete={vi.fn()}
      />,
    );

    await user.click(screen.getByRole('button', { name: /승인/ }));
    expect(onApprove).toHaveBeenCalledWith(7);
  });

  it('shows suspend button for ACTIVE users and hides approve', () => {
    render(
      <AdminUserTable
        users={[make({ id: 1, status: 'ACTIVE' })]}
        onApprove={vi.fn()}
        onSuspend={vi.fn()}
        onDelete={vi.fn()}
      />,
    );
    expect(screen.queryByRole('button', { name: /승인/ })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /정지/ })).toBeInTheDocument();
  });

  it('invokes onDelete for any user', async () => {
    const onDelete = vi.fn();
    const user = userEvent.setup();
    render(
      <AdminUserTable
        users={[make({ id: 5, status: 'SUSPENDED' })]}
        onApprove={vi.fn()}
        onSuspend={vi.fn()}
        onDelete={onDelete}
      />,
    );
    await user.click(screen.getByRole('button', { name: /삭제/ }));
    expect(onDelete).toHaveBeenCalledWith(5);
  });
});

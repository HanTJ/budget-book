'use client';

import type { AdminUser } from '@/lib/schemas/admin';

interface Props {
  users: AdminUser[];
  onApprove: (id: number) => void;
  onSuspend: (id: number) => void;
  onDelete: (id: number) => void;
}

const STATUS_LABEL: Record<AdminUser['status'], string> = {
  PENDING: '승인 대기',
  ACTIVE: '활성',
  SUSPENDED: '정지',
};

export function AdminUserTable({ users, onApprove, onSuspend, onDelete }: Props) {
  if (users.length === 0) {
    return <p className="text-gray-600">표시할 회원이 없습니다.</p>;
  }

  return (
    <table className="w-full rounded border">
      <thead className="bg-gray-50">
        <tr>
          <th className="px-4 py-2 text-left">이메일</th>
          <th className="px-4 py-2 text-left">이름</th>
          <th className="px-4 py-2 text-left">권한</th>
          <th className="px-4 py-2 text-left">상태</th>
          <th className="px-4 py-2 text-right">작업</th>
        </tr>
      </thead>
      <tbody>
        {users.map((user) => (
          <tr key={user.id} className="border-t">
            <td className="px-4 py-2">{user.email}</td>
            <td className="px-4 py-2">{user.display_name}</td>
            <td className="px-4 py-2">{user.role}</td>
            <td className="px-4 py-2">
              <span
                className={
                  user.status === 'ACTIVE'
                    ? 'text-green-700'
                    : user.status === 'PENDING'
                      ? 'text-yellow-700'
                      : 'text-red-700'
                }
              >
                {STATUS_LABEL[user.status]}
              </span>
            </td>
            <td className="px-4 py-2">
              <div className="flex justify-end gap-2 text-sm">
                {user.status === 'PENDING' && (
                  <button
                    type="button"
                    onClick={() => onApprove(user.id)}
                    className="rounded bg-green-600 px-3 py-1 text-white"
                  >
                    승인
                  </button>
                )}
                {user.status === 'ACTIVE' && (
                  <button
                    type="button"
                    onClick={() => onSuspend(user.id)}
                    className="rounded border border-yellow-600 px-3 py-1 text-yellow-800"
                  >
                    정지
                  </button>
                )}
                <button
                  type="button"
                  onClick={() => onDelete(user.id)}
                  className="rounded border border-red-600 px-3 py-1 text-red-700"
                >
                  삭제
                </button>
              </div>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

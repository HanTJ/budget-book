'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { AdminUserTable } from '@/components/admin/AdminUserTable';
import * as adminApi from '@/lib/api/admin';
import * as authApi from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import type { AdminUser } from '@/lib/schemas/admin';
import { useAuthStore } from '@/lib/stores/auth';

type Filter = 'ALL' | 'PENDING' | 'ACTIVE' | 'SUSPENDED';

export default function AdminUsersPage() {
  const router = useRouter();
  const token = useAuthStore((s) => s.accessToken);
  const [filter, setFilter] = useState<Filter>('ALL');
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!token) return;
    try {
      const list = await adminApi.listAdminUsers(
        token,
        filter === 'ALL' ? undefined : filter,
      );
      setUsers(list);
      setError(null);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        router.replace('/login');
        return;
      }
      if (e instanceof ApiError && e.status === 403) {
        setError('관리자 권한이 필요합니다.');
        return;
      }
      setError('회원 목록을 불러오지 못했습니다.');
    }
  }, [token, router, filter]);

  useEffect(() => {
    if (!token) {
      router.replace('/login');
      return;
    }
    authApi
      .me(token)
      .then((me) => {
        if (me.role !== 'ADMIN') {
          setError('관리자 권한이 필요합니다.');
          return;
        }
        void reload();
      })
      .catch(() => {
        router.replace('/login');
      });
  }, [token, router, reload]);

  const approve = async (id: number): Promise<void> => {
    if (!token) return;
    await adminApi.patchAdminUser(token, id, { status: 'ACTIVE' });
    await reload();
  };

  const suspend = async (id: number): Promise<void> => {
    if (!token) return;
    await adminApi.patchAdminUser(token, id, { status: 'SUSPENDED' });
    await reload();
  };

  const remove = async (id: number): Promise<void> => {
    if (!token) return;
    await adminApi.deleteAdminUser(token, id);
    await reload();
  };

  if (!token) return null;

  return (
    <main className="mx-auto flex min-h-screen max-w-5xl flex-col gap-6 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">회원 관리</h1>
        <a href="/dashboard" className="text-sm text-blue-600">
          ← 대시보드
        </a>
      </header>

      <div className="flex gap-2 text-sm">
        {(['ALL', 'PENDING', 'ACTIVE', 'SUSPENDED'] as const).map((f) => (
          <button
            key={f}
            type="button"
            onClick={() => setFilter(f)}
            className={`rounded border px-3 py-1 ${
              filter === f ? 'bg-black text-white' : ''
            }`}
          >
            {f}
          </button>
        ))}
      </div>

      {error && <p className="text-red-600">{error}</p>}
      {!error && (
        <AdminUserTable
          users={users}
          onApprove={(id) => void approve(id)}
          onSuspend={(id) => void suspend(id)}
          onDelete={(id) => void remove(id)}
        />
      )}
    </main>
  );
}

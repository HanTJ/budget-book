'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { AccountForm } from '@/components/accounts/AccountForm';
import { AccountList } from '@/components/accounts/AccountList';
import * as accountsApi from '@/lib/api/accounts';
import { ApiError } from '@/lib/api/client';
import type { Account, CreateAccountInput } from '@/lib/schemas/accounts';
import { useAuthHydrated, useAuthStore } from '@/lib/stores/auth';

export default function AccountsPage() {
  const router = useRouter();
  const hydrated = useAuthHydrated();
  const token = useAuthStore((s) => s.accessToken);
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!token) return;
    try {
      setAccounts(await accountsApi.listAccounts(token));
      setError(null);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        router.replace('/login');
        return;
      }
      setError('계정 목록을 불러오지 못했습니다.');
    }
  }, [token, router]);

  useEffect(() => {
    if (!hydrated) return;
    if (!token) {
      router.replace('/login');
      return;
    }
    void reload();
  }, [hydrated, token, router, reload]);

  if (!hydrated) return null;
  if (!token) return null;

  const onCreate = async (payload: CreateAccountInput): Promise<void> => {
    await accountsApi.createAccount(token, payload);
    await reload();
  };

  const onDelete = async (id: number): Promise<void> => {
    await accountsApi.deleteAccount(token, id);
    await reload();
  };

  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">계정 관리</h1>
        <a href="/dashboard" className="text-sm text-blue-600">
          ← 대시보드
        </a>
      </header>
      {error && <p className="text-red-600">{error}</p>}
      <AccountList accounts={accounts} onDelete={(id) => void onDelete(id)} />
      <AccountForm onCreate={onCreate} />
    </main>
  );
}

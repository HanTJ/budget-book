'use client';

import { useCallback, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { DailyEntryList } from '@/components/entries/DailyEntryList';
import { EntryForm } from '@/components/entries/EntryForm';
import * as accountsApi from '@/lib/api/accounts';
import { ApiError } from '@/lib/api/client';
import * as entriesApi from '@/lib/api/entries';
import type { Account } from '@/lib/schemas/accounts';
import type { JournalEntry, RecordEntryInput } from '@/lib/schemas/entries';
import { useAuthStore } from '@/lib/stores/auth';

function monthRange(): { from: string; to: string } {
  const now = new Date();
  const year = now.getFullYear();
  const month = now.getMonth();
  const first = new Date(year, month, 1).toISOString().slice(0, 10);
  const last = new Date(year, month + 1, 0).toISOString().slice(0, 10);
  return { from: first, to: last };
}

export default function TransactionsPage() {
  const router = useRouter();
  const token = useAuthStore((s) => s.accessToken);
  const [accounts, setAccounts] = useState<Account[]>([]);
  const [entries, setEntries] = useState<JournalEntry[]>([]);
  const [error, setError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    if (!token) return;
    try {
      const { from, to } = monthRange();
      const [accountList, entryList] = await Promise.all([
        accountsApi.listAccounts(token),
        entriesApi.listEntries(token, from, to),
      ]);
      setAccounts(accountList);
      setEntries(entryList);
      setError(null);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) {
        router.replace('/login');
        return;
      }
      setError('데이터를 불러오지 못했습니다.');
    }
  }, [token, router]);

  useEffect(() => {
    if (!token) {
      router.replace('/login');
      return;
    }
    void reload();
  }, [token, router, reload]);

  if (!token) return null;

  const onRecord = async (input: RecordEntryInput): Promise<void> => {
    try {
      await entriesApi.recordEntry(token, input);
      await reload();
    } catch (e) {
      if (e instanceof ApiError) {
        const detail =
          e.details && typeof e.details === 'object' && 'details' in e.details
            ? JSON.stringify(e.details)
            : e.code;
        throw new Error(detail);
      }
      throw e;
    }
  };

  const onDelete = async (id: number): Promise<void> => {
    await entriesApi.deleteEntry(token, id);
    await reload();
  };

  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col gap-6 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">거래 기록</h1>
        <a href="/dashboard" className="text-sm text-blue-600">
          ← 대시보드
        </a>
      </header>
      {error && <p className="text-red-600">{error}</p>}
      <EntryForm accounts={accounts} onSubmit={onRecord} />
      <DailyEntryList entries={entries} onDelete={(id) => void onDelete(id)} />
    </main>
  );
}

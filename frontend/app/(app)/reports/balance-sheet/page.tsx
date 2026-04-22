'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { BalanceSheetView } from '@/components/reports/BalanceSheetView';
import * as reportsApi from '@/lib/api/reports';
import { ApiError } from '@/lib/api/client';
import type { BalanceSheet } from '@/lib/schemas/reports';
import { useAuthStore } from '@/lib/stores/auth';

export default function BalanceSheetPage() {
  const router = useRouter();
  const token = useAuthStore((s) => s.accessToken);
  const [on, setOn] = useState<string>(() => new Date().toISOString().slice(0, 10));
  const [sheet, setSheet] = useState<BalanceSheet | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      router.replace('/login');
      return;
    }
    reportsApi
      .fetchBalanceSheet(token, on)
      .then((data) => {
        setSheet(data);
        setError(null);
      })
      .catch((e: unknown) => {
        if (e instanceof ApiError && e.status === 401) {
          router.replace('/login');
          return;
        }
        setError('재무상태표를 불러오지 못했습니다.');
      });
  }, [token, router, on]);

  if (!token) return null;

  return (
    <main className="mx-auto flex min-h-screen max-w-4xl flex-col gap-6 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">재무상태표</h1>
        <a href="/dashboard" className="text-sm text-blue-600">
          ← 대시보드
        </a>
      </header>

      <label className="flex items-center gap-3 text-sm">
        기준일:
        <input
          type="date"
          value={on}
          onChange={(e) => setOn(e.target.value)}
          className="rounded border p-2"
        />
      </label>

      {error && <p className="text-red-600">{error}</p>}
      {sheet && <BalanceSheetView sheet={sheet} />}
    </main>
  );
}

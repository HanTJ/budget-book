'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { CashFlowView } from '@/components/reports/CashFlowView';
import * as reportsApi from '@/lib/api/reports';
import { ApiError } from '@/lib/api/client';
import type { CashFlowStatement } from '@/lib/schemas/reports';
import { useAuthStore } from '@/lib/stores/auth';

function monthRange(): { from: string; to: string } {
  const now = new Date();
  const first = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10);
  const last = new Date(now.getFullYear(), now.getMonth() + 1, 0).toISOString().slice(0, 10);
  return { from: first, to: last };
}

export default function CashFlowPage() {
  const router = useRouter();
  const token = useAuthStore((s) => s.accessToken);
  const initial = monthRange();
  const [from, setFrom] = useState<string>(initial.from);
  const [to, setTo] = useState<string>(initial.to);
  const [statement, setStatement] = useState<CashFlowStatement | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      router.replace('/login');
      return;
    }
    reportsApi
      .fetchCashFlow(token, from, to)
      .then((data) => {
        setStatement(data);
        setError(null);
      })
      .catch((e: unknown) => {
        if (e instanceof ApiError && e.status === 401) {
          router.replace('/login');
          return;
        }
        setError('현금흐름표를 불러오지 못했습니다.');
      });
  }, [token, router, from, to]);

  if (!token) return null;

  return (
    <main className="mx-auto flex min-h-screen max-w-4xl flex-col gap-6 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">현금흐름표</h1>
        <a href="/dashboard" className="text-sm text-blue-600">
          ← 대시보드
        </a>
      </header>

      <div className="flex items-center gap-3 text-sm">
        <label className="flex items-center gap-2">
          시작:
          <input
            type="date"
            value={from}
            onChange={(e) => setFrom(e.target.value)}
            className="rounded border p-2"
          />
        </label>
        <label className="flex items-center gap-2">
          종료:
          <input
            type="date"
            value={to}
            onChange={(e) => setTo(e.target.value)}
            className="rounded border p-2"
          />
        </label>
      </div>

      {error && <p className="text-red-600">{error}</p>}
      {statement && <CashFlowView statement={statement} />}
    </main>
  );
}

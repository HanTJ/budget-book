'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import * as authApi from '@/lib/api/auth';
import type { Me } from '@/lib/schemas/auth';
import { useAuthStore } from '@/lib/stores/auth';

export default function DashboardPage() {
  const router = useRouter();
  const token = useAuthStore((s) => s.accessToken);
  const clear = useAuthStore((s) => s.clear);
  const [me, setMe] = useState<Me | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!token) {
      router.replace('/login');
      return;
    }
    authApi
      .me(token)
      .then(setMe)
      .catch(() => setError('세션이 만료되었습니다. 다시 로그인해주세요.'));
  }, [token, router]);

  if (!token) return null;

  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col gap-4 p-8">
      <header className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">대시보드</h1>
        <button
          onClick={() => {
            clear();
            router.push('/login');
          }}
          className="rounded border px-3 py-1 text-sm"
        >
          로그아웃
        </button>
      </header>
      {error && <p className="text-red-600">{error}</p>}
      {me && (
        <section className="rounded border p-4">
          <p className="text-lg">환영합니다, {me.display_name} 님</p>
          <p className="text-sm text-gray-600">이메일: {me.email}</p>
          <p className="text-sm text-gray-600">권한: {me.role}</p>
        </section>
      )}
      <nav className="flex flex-wrap gap-3">
        <a href="/accounts" className="rounded border px-4 py-2">
          계정 관리
        </a>
        <a href="/transactions" className="rounded border px-4 py-2">
          거래 기록
        </a>
        <a href="/reports/balance-sheet" className="rounded border px-4 py-2">
          재무상태표
        </a>
        <a href="/reports/cash-flow" className="rounded border px-4 py-2">
          현금흐름표
        </a>
        {me?.role === 'ADMIN' && (
          <a href="/admin/users" className="rounded bg-red-600 px-4 py-2 text-white">
            회원 관리
          </a>
        )}
      </nav>
    </main>
  );
}

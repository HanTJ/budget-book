'use client';

import { useRouter } from 'next/navigation';
import { LoginForm } from '@/components/auth/LoginForm';
import * as authApi from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import type { LoginInput } from '@/lib/schemas/auth';
import { useAuthStore } from '@/lib/stores/auth';

export default function LoginPage() {
  const router = useRouter();
  const setTokens = useAuthStore((s) => s.setTokens);

  const onSubmit = async (payload: LoginInput): Promise<void> => {
    try {
      const tokens = await authApi.login(payload);
      setTokens(tokens);
      router.push('/dashboard');
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(error.code);
      }
      throw error;
    }
  };

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 p-8">
      <h1 className="text-2xl font-bold">로그인</h1>
      <LoginForm onSubmit={onSubmit} />
    </main>
  );
}

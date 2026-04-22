'use client';

import { RegisterForm } from '@/components/auth/RegisterForm';
import * as authApi from '@/lib/api/auth';
import { ApiError } from '@/lib/api/client';
import type { RegisterInput } from '@/lib/schemas/auth';

export default function RegisterPage() {
  const onSubmit = async (payload: RegisterInput): Promise<void> => {
    try {
      await authApi.register(payload);
    } catch (error) {
      if (error instanceof ApiError) {
        throw new Error(error.code);
      }
      throw error;
    }
  };

  return (
    <main className="mx-auto flex min-h-screen max-w-md flex-col justify-center gap-6 p-8">
      <h1 className="text-2xl font-bold">회원가입</h1>
      <RegisterForm onSubmit={onSubmit} />
    </main>
  );
}

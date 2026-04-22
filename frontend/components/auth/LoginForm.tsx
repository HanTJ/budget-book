'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { type LoginInput, loginSchema } from '@/lib/schemas/auth';

interface Props {
  onSubmit: (payload: LoginInput) => Promise<void>;
}

const ERROR_MESSAGE: Record<string, string> = {
  invalid_credentials: '이메일 또는 비밀번호가 올바르지 않습니다.',
  account_pending: '계정이 승인 대기 중입니다. 관리자 승인 후 이용해주세요.',
  account_suspended: '계정이 정지되었습니다. 관리자에게 문의하세요.',
};

export function LoginForm({ onSubmit }: Props) {
  const [serverError, setServerError] = useState<string | null>(null);

  const form = useForm<LoginInput>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const handle = form.handleSubmit(async (values) => {
    setServerError(null);
    try {
      await onSubmit(values);
    } catch (error) {
      const code = error instanceof Error ? error.message : 'unknown_error';
      setServerError(ERROR_MESSAGE[code] ?? '로그인 중 오류가 발생했습니다.');
    }
  });

  return (
    <form onSubmit={handle} className="flex flex-col gap-4" noValidate>
      <label className="flex flex-col gap-1">
        <span>이메일</span>
        <input
          type="email"
          autoComplete="email"
          className="rounded border p-2"
          {...form.register('email')}
        />
        {form.formState.errors.email && (
          <span className="text-sm text-red-600">{form.formState.errors.email.message}</span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>비밀번호</span>
        <input
          type="password"
          autoComplete="current-password"
          className="rounded border p-2"
          {...form.register('password')}
        />
        {form.formState.errors.password && (
          <span className="text-sm text-red-600">{form.formState.errors.password.message}</span>
        )}
      </label>

      {serverError && <p className="text-sm text-red-600">{serverError}</p>}

      <button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="rounded bg-black p-2 text-white disabled:opacity-50"
      >
        로그인
      </button>
    </form>
  );
}

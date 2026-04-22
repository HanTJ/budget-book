'use client';

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { type RegisterInput, registerSchema } from '@/lib/schemas/auth';

interface Props {
  onSubmit: (payload: RegisterInput) => Promise<void>;
}

export function RegisterForm({ onSubmit }: Props) {
  const [submitted, setSubmitted] = useState(false);
  const [serverError, setServerError] = useState<string | null>(null);

  const form = useForm<RegisterInput>({
    resolver: zodResolver(registerSchema),
    defaultValues: { email: '', password: '', display_name: '' },
  });

  const handle = form.handleSubmit(async (values) => {
    setServerError(null);
    try {
      await onSubmit(values);
      setSubmitted(true);
    } catch (error) {
      const message = error instanceof Error ? error.message : 'unknown_error';
      setServerError(
        message === 'email_already_registered'
          ? '이미 등록된 이메일입니다.'
          : '가입 중 오류가 발생했습니다.',
      );
    }
  });

  if (submitted) {
    return (
      <div role="status" className="rounded bg-green-50 p-4 text-green-900">
        가입 요청이 접수되었습니다. 관리자 승인 후 로그인할 수 있습니다.
      </div>
    );
  }

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
          autoComplete="new-password"
          className="rounded border p-2"
          {...form.register('password')}
        />
        {form.formState.errors.password && (
          <span className="text-sm text-red-600">{form.formState.errors.password.message}</span>
        )}
      </label>

      <label className="flex flex-col gap-1">
        <span>표시 이름</span>
        <input
          type="text"
          autoComplete="name"
          className="rounded border p-2"
          {...form.register('display_name')}
        />
        {form.formState.errors.display_name && (
          <span className="text-sm text-red-600">
            {form.formState.errors.display_name.message}
          </span>
        )}
      </label>

      {serverError && <p className="text-sm text-red-600">{serverError}</p>}

      <button
        type="submit"
        disabled={form.formState.isSubmitting}
        className="rounded bg-black p-2 text-white disabled:opacity-50"
      >
        가입
      </button>
    </form>
  );
}

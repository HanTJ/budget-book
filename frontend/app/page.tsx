import Link from 'next/link';

export default function HomePage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-3xl flex-col items-center justify-center gap-4 p-8">
      <h1 className="text-4xl font-bold">Budget Book</h1>
      <p className="text-lg text-gray-600">
        현금흐름표 · 재무상태표 기반 웹 가계부
      </p>
      <nav className="flex gap-4">
        <Link href="/login" className="rounded border px-4 py-2">
          로그인
        </Link>
        <Link href="/register" className="rounded bg-black px-4 py-2 text-white">
          회원가입
        </Link>
      </nav>
    </main>
  );
}

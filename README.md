# Budget Book (가계부)

기업 회계의 **현금흐름표(Cash Flow Statement)** 와 **재무상태표(Balance Sheet)** 형식을 적용한 웹 기반 개인 가계부.

- Backend: PHP 8.4 + Slim 4 + Eloquent
- Frontend: Next.js 15 (App Router) + TypeScript
- DB: MySQL 8
- 완전 복식부기(double-entry) 저장, KRW 전용, soft delete

## 문서

- [`PLAN.md`](./PLAN.md) — 구현 계획
- [`CLAUDE.md`](./CLAUDE.md) — 기술 헌법 + TDD 하네스

## 빠른 시작

```bash
make env           # .env 생성
make up            # mysql / php / nginx 기동
make install       # composer install + npm install
make migrate       # DB 스키마
make test          # PHPUnit + Vitest
make ci            # lint + typecheck + test (커밋 직전 필수)
```

## 개발 원칙

**TDD 필수.** 모든 기능은 실패 테스트(RED) → 최소 구현(GREEN) → 리팩터 순으로 진행합니다. 자세한 규칙은 `CLAUDE.md §3` 참조.

# Budget Book (가계부)

기업 회계의 **현금흐름표(Cash Flow Statement)** 와 **재무상태표(Balance Sheet)** 형식을 적용한 웹 기반 개인 가계부. 사용자 입력(금액/지불방식/사용처/카테고리)을 서버가 **완전 복식부기 분개(journal entry)** 로 자동 변환해 저장합니다.

## 기술 스택

| 영역 | 스택 |
| --- | --- |
| Backend | PHP 8.4 · Slim 4 · PHP-DI · Eloquent(Query Builder) · Phinx · Firebase JWT v7 · brick/math |
| Frontend | Next.js 15 (App Router) · TypeScript strict · TanStack Query · Zustand · React Hook Form + Zod · Tailwind CSS |
| DB | MySQL 8 (utf8mb4_0900_ai_ci) |
| 테스트 | PHPUnit 11 · PHPStan level 8 · PHP-CS-Fixer · Vitest + React Testing Library · Playwright · ESLint flat + Prettier |
| 런타임 | Docker Compose (MySQL + PHP-FPM + nginx) · Node 20+ |

통화는 **원화(KRW) 전용**, 삭제는 **soft delete** 로 고정.

## 문서

- [`PLAN.md`](./PLAN.md) — Phase 별 구현 계획 + 확정된 결정사항
- [`CLAUDE.md`](./CLAUDE.md) — 기술 헌법 + TDD 하네스 규칙 (변경 금지 조항 포함)
- [`ARCHITECTURE.md`](./ARCHITECTURE.md) — 레이어 구성, 복식부기 불변식, 스키마/인덱스 맵

## 빠른 시작

```bash
make env           # .env 생성 (.env.example 복사)
make up            # mysql / php / nginx 기동
make install       # composer install + npm install
make migrate       # 개발 DB 마이그레이션
make migrate-test  # 테스트 DB 마이그레이션
make admin-seed    # 최초 관리자 계정 생성/갱신 (.env 의 INITIAL_ADMIN_* 사용)
make test          # PHPUnit + Vitest
make ci            # lint + typecheck + test 전부 (커밋 직전 필수)
```

- 백엔드 API: `http://localhost:8080/api`
- 프론트엔드 개발 서버: `npm --prefix frontend run dev` → `http://localhost:3000`
- MySQL (host 포트): `3307`

## 진행 현황

| Phase | 상태 | 내용 |
| --- | --- | --- |
| 0 — 스캐폴딩 | ✅ | Docker + Slim 4 + Next.js + TDD 하네스, `GET /api/health` |
| 1 — 인증 | ✅ | 회원가입(PENDING) / 로그인 / `GET /api/me` / JWT 미들웨어 / CORS |
| 2 — 원장 | ✅ | 5 계정과목 + 기본 27계정 시드 + Account CRUD + ApproveUser 유스케이스 |
| 3 — 분개(복식부기) | ✅ | `journal_entries` + `lines` (DB CHECK 차대 배타), 자동 분개 변환, `/api/entries` |
| 4 — 보고서 | ✅ | 재무상태표(자산=부채+자본 assert) · 현금흐름표(직접법, Σ3섹션=현금증감 assert) · 일별 |
| 5 — 관리자 | ✅ | AdminAuth 미들웨어 · `/api/admin/users` CRUD · 가입 승인(시드 자동) · 관리자 UI |
| 6 — E2E/성능/문서 | ✅ | 전체 플로우 E2E + 인덱스 EXPLAIN 검증 + PATCH 분개 + ARCHITECTURE.md |

## 현재 사용 가능한 API

- `GET  /api/health`
- `POST /api/auth/register` · `POST /api/auth/login` · `GET /api/me`
- `GET/POST /api/accounts` · `PATCH/DELETE /api/accounts/{id}`
- `GET/POST /api/entries` (`?from=&to=`) · `PATCH/DELETE /api/entries/{id}`
- `GET /api/reports/balance-sheet?on=` · `GET /api/reports/cash-flow?from=&to=` · `GET /api/reports/daily?date=`
- `GET /api/admin/users?status=` · `PATCH /api/admin/users/{id}` · `DELETE /api/admin/users/{id}` (ADMIN 전용)

모든 `/api/*` (인증 제외) 는 Bearer JWT 필요. 타 사용자의 데이터 접근 시 404.

## 현재 사용 가능한 화면

- `/` 홈 · `/register` 가입 (PENDING 안내) · `/login` 로그인
- `/dashboard` 사용자 정보
- `/accounts` 계정 관리 (시스템 기본 계정은 삭제 불가)
- `/transactions` 거래 입력 + 일별 목록
- `/reports/balance-sheet` 재무상태표 (자산/부채/자본 + 항등식 성립 표시)
- `/reports/cash-flow` 현금흐름표 (영업/투자/재무 섹션 + 조정 일치 표시)
- `/admin/users` ADMIN 전용 회원 관리 (승인 / 정지 / 삭제)

## 테스트 현황

- Backend: **162 PHPUnit** (Unit + Integration + Feature, 510 assertions)
- Frontend: **28 Vitest** (컴포넌트 테스트)
- E2E: **4 Playwright** (register pending / login account_pending / admin 승인+계정 / 전 플로우 기록→보고서)
- PHPStan level 8, ESLint flat, PHP-CS-Fixer PSR-12 전부 clean

## 개발 원칙

**TDD 필수.** 모든 기능은 실패 테스트(RED) → 최소 구현(GREEN) → 리팩터 순으로 진행합니다. 세부 규칙 — 커버리지 게이트, 테스트 계층, Clock/IdGenerator 주입, 스택 변경 금지 조항 — 은 [`CLAUDE.md §3`](./CLAUDE.md) 참조.

### 단일 테스트 실행

```bash
# Backend
docker compose run --rm php vendor/bin/phpunit --filter JournalEntry

# Frontend
npm --prefix frontend run test -- tests/unit/EntryForm.test.tsx

# E2E (nginx:8080 + Next dev 3000 필요)
cd frontend && npx playwright test tests/e2e/register-login.spec.ts
```

# PLAN.md — 웹 기반 가계부 프로그램 구현 계획

## 1. 프로젝트 개요

기업 회계의 **현금흐름표(Cash Flow Statement)** 와 **재무상태표(Balance Sheet, 흔히 "재무재표"로 지칭)** 형식을 개인 가계부에 적용한 웹 서비스.

사용자가 지출/수입 내역(금액, 지불방식, 사용처)을 입력하면, 이를 기업 회계 기준으로 분류·저장하고, 일별 재무상태표 / 기간별 현금흐름표를 조회할 수 있다.

### 사용자 구분
- **일반 사용자 (USER)**: 본인 가계부 입력/조회
- **관리자 (ADMIN)**: 전체 회원 관리(가입 승인, 비활성화, 권한 변경 등)

---

## 2. 기술 스택 (고정)

| 영역 | 스택 | 비고 |
| --- | --- | --- |
| Backend | PHP 8.4 + Slim Framework 4 | REST API. 경량 프레임워크 채택. |
| Backend DI/Router | PHP-DI, Slim Routing | |
| ORM | Eloquent (`illuminate/database`) | Migration은 Phinx로 분리 |
| Auth | JWT (firebase/php-jwt) + bcrypt | Access + Refresh 토큰 |
| Backend 테스트 | PHPUnit 11.x | 커버리지 80% 이상 목표 |
| 정적 분석 | PHPStan level 8, PHP-CS-Fixer | |
| Frontend | Next.js 15 (App Router) + TypeScript strict | |
| Frontend 상태 | TanStack Query (서버 상태), Zustand (UI 상태) | |
| 스타일링 | Tailwind CSS | |
| 폼/검증 | React Hook Form + Zod | |
| Frontend 테스트 | Vitest + React Testing Library | 단위/컴포넌트 |
| E2E | Playwright | 핵심 사용자 플로우 |
| Lint/Format | ESLint (flat config), Prettier | |
| DB | MySQL 8 (utf8mb4_0900_ai_ci) | |
| 로컬 실행 | Docker Compose (php-fpm, nginx, mysql, node) | |
| CI | GitHub Actions (옵션) | 로컬 pre-commit hook 필수 |

> **제약**: 위 스택은 변경 불가. 대체 프레임워크/언어 도입 금지 (CLAUDE.md의 기술 헌법 참조).

---

## 3. 디렉토리 구조

```
budget-book/
├── backend/
│   ├── public/index.php            # Slim 진입점
│   ├── src/
│   │   ├── Domain/                 # 엔티티, 값 객체, 도메인 서비스
│   │   │   ├── Account/            # User, Role
│   │   │   ├── Ledger/             # Transaction, Category, PaymentMethod
│   │   │   └── Reporting/          # CashFlowStatement, BalanceSheet
│   │   ├── Application/            # 유스케이스 (Action/Handler)
│   │   ├── Infrastructure/         # DB Repository, JWT, Eloquent mapping
│   │   └── Interface/Http/         # Controller, Middleware, Routes
│   ├── tests/
│   │   ├── Unit/                   # Domain + Application 순수 테스트
│   │   ├── Integration/            # DB 연동 (MySQL testcontainer)
│   │   └── Feature/                # HTTP endpoint (PSR-7 요청/응답)
│   ├── database/
│   │   ├── migrations/
│   │   └── seeds/
│   ├── composer.json
│   ├── phpunit.xml
│   ├── phpstan.neon
│   └── .php-cs-fixer.php
├── frontend/
│   ├── app/                        # Next.js App Router
│   │   ├── (auth)/login/
│   │   ├── (auth)/register/
│   │   ├── (app)/dashboard/
│   │   ├── (app)/transactions/
│   │   ├── (app)/reports/
│   │   │   ├── cash-flow/
│   │   │   └── balance-sheet/
│   │   └── (admin)/admin/users/
│   ├── components/
│   ├── lib/
│   │   ├── api/                    # fetch wrapper, auth
│   │   └── schemas/                # Zod
│   ├── tests/
│   │   ├── unit/
│   │   └── e2e/                    # Playwright specs
│   ├── package.json
│   ├── vitest.config.ts
│   └── playwright.config.ts
├── docker/
│   ├── php/Dockerfile
│   ├── nginx/default.conf
│   └── mysql/init.sql
├── docker-compose.yml
├── .gitignore
├── README.md
├── PLAN.md                         # (본 파일)
└── CLAUDE.md                       # 기술 헌법 + TDD 하네스 규칙
```

---

## 4. 데이터 모델 (MySQL 8 스키마 개요) — **완전 복식부기**

> 사용자 입력 UX 는 단순하게(금액/지불방식/사용처/카테고리) 유지하지만, **저장은 반드시 복식부기 분개(journal entry)** 로 이루어진다. 서비스 레이어가 입력을 분개로 변환하며, 모든 분개는 `차변 합계 = 대변 합계` 를 만족해야 한다 (DB 제약 + 도메인 불변식).

### 4.1 `users`
| 컬럼 | 타입 | 설명 |
| --- | --- | --- |
| id | BIGINT PK AI | |
| email | VARCHAR(190) UNIQUE | 로그인 ID |
| password_hash | VARCHAR(255) | bcrypt |
| display_name | VARCHAR(100) | |
| role | ENUM('USER','ADMIN') | |
| status | ENUM('PENDING','ACTIVE','SUSPENDED') | 가입 직후 `PENDING`, 관리자가 `ACTIVE` 로 전환 |
| deleted_at | DATETIME NULL | **soft delete** 표식 |
| created_at / updated_at | DATETIME | |

### 4.2 `accounts` (원장 계정 — 5 계정과목)
복식부기 원장. **모든 거래는 이 테이블의 계정끼리 차변/대변으로 이동**.

| 컬럼 | 타입 | 설명 |
| --- | --- | --- |
| id | BIGINT PK | |
| user_id | BIGINT FK | 사용자별 독립 원장 |
| name | VARCHAR(100) | 예: '국민은행 입출금', '신한카드', '식비', '급여' |
| account_type | ENUM('ASSET','LIABILITY','EQUITY','INCOME','EXPENSE') | |
| subtype | VARCHAR(40) NULL | 예: 'CASH','BANK','CARD','LOAN','INVESTMENT' (ASSET/LIABILITY 세분) |
| cash_flow_section | ENUM('OPERATING','INVESTING','FINANCING','NONE') | INCOME/EXPENSE/자본변동 계정에 부여, 기타 'NONE' |
| normal_balance | ENUM('DEBIT','CREDIT') | 계정과목별 정상잔액. ASSET/EXPENSE=DEBIT, LIABILITY/EQUITY/INCOME=CREDIT |
| opening_balance | DECIMAL(18,2) DEFAULT 0 | 초기 잔액 (정상잔액 부호 기준) |
| is_system | TINYINT(1) | 시드된 시스템 기본 계정 여부 |
| deleted_at | DATETIME NULL | soft delete |

> 기존 "카테고리" 개념은 **INCOME/EXPENSE 계정과목** 으로 흡수된다. 별도 `categories` 테이블 없음.

### 4.3 `journal_entries` (분개 헤더)
| 컬럼 | 타입 | 설명 |
| --- | --- | --- |
| id | BIGINT PK | |
| user_id | BIGINT FK | |
| occurred_on | DATE | 일별 재무상태표 스냅샷 기준 |
| memo | VARCHAR(255) NULL | |
| merchant | VARCHAR(200) NULL | 사용처 (UI 입력값) |
| payment_method | ENUM('CASH','CARD','TRANSFER') NULL | UI 입력값. 실제 회계는 line 의 account 로 판정 |
| source | ENUM('USER','SYSTEM') DEFAULT 'USER' | 개업분개/감가상각 등 시스템 생성분 구분용 |
| deleted_at | DATETIME NULL | soft delete |
| created_at | DATETIME | |

### 4.4 `journal_entry_lines` (분개 행)
| 컬럼 | 타입 | 설명 |
| --- | --- | --- |
| id | BIGINT PK | |
| entry_id | BIGINT FK journal_entries | |
| account_id | BIGINT FK accounts | |
| debit | DECIMAL(18,2) DEFAULT 0 | 둘 중 **정확히 하나만 > 0** |
| credit | DECIMAL(18,2) DEFAULT 0 | |
| line_no | SMALLINT | 0-based 순서 |

**DB 제약**
- `CHECK (debit >= 0 AND credit >= 0)`
- `CHECK ((debit = 0) <> (credit = 0))` — 한쪽만 0
- 트리거 또는 Application 레이어에서 **분개별 `SUM(debit) = SUM(credit)`** 강제 (저장 시 트랜잭션 내 검증).

### 4.5 인덱스
- `accounts(user_id, account_type)`
- `journal_entries(user_id, occurred_on)`
- `journal_entry_lines(account_id, entry_id)`

### 4.6 회계 로직 (복식부기 규칙)

**입력 → 분개 변환 (자동화)**

| 사용자 입력 | 예시 | 차변(Dr) | 대변(Cr) |
| --- | --- | --- | --- |
| 카드 결제 지출 (식비 10,000, 신한카드) | 일상 지출 | 식비 (EXPENSE) 10,000 | 신한카드 (LIABILITY) 10,000 |
| 현금 지출 (식비 5,000) | | 식비 (EXPENSE) 5,000 | 현금 (ASSET) 5,000 |
| 계좌이체 지출 (공과금 70,000, 국민은행) | | 공과금 (EXPENSE) 70,000 | 국민은행 (ASSET) 70,000 |
| 급여 수령 (3,000,000, 국민은행 입금) | | 국민은행 (ASSET) 3,000,000 | 급여 (INCOME) 3,000,000 |
| 주식 매수 (500,000, 증권계좌) | 투자활동 | 증권계좌-주식 (ASSET) 500,000 | 증권계좌-현금 (ASSET) 500,000 |
| 대출 수령 (10,000,000, 국민은행) | 재무활동 | 국민은행 (ASSET) 10,000,000 | 마이너스대출 (LIABILITY) 10,000,000 |
| 대출 상환 (500,000) | 재무활동 | 마이너스대출 (LIABILITY) 500,000 | 국민은행 (ASSET) 500,000 |
| 카드 대금 결제 (500,000, 국민→신한카드) | 이체 | 신한카드 (LIABILITY) 500,000 | 국민은행 (ASSET) 500,000 |

**보고서 산출**

- **재무상태표(`on=D`)**:
  - 각 `accounts` 의 잔액 = `opening_balance + Σ(해당일 D까지 차변 − 대변)` — normal_balance 부호 기준으로 정규화.
  - 자산 = Σ(ASSET 잔액), 부채 = Σ(LIABILITY 잔액), 자본 = Σ(EQUITY 잔액) + 당기순이익(INCOME−EXPENSE, 시작일~D).
  - 항등식 검증: `자산 = 부채 + 자본` (테스트에서 assert).
- **현금흐름표(`from ~ to`)**:
  - **직접법 기반**: 기간 내 모든 분개 중 **현금성 계정**(ASSET subtype ∈ CASH,BANK) 이 관여한 라인을 추출 → 상대편 라인의 `account.cash_flow_section` 으로 분류 집계.
  - 영업/투자/재무 3구간, INFLOW(현금 차변)/OUTFLOW(현금 대변) 분리.
  - 말미 검증: `Σ(3구간 순증감) = 현금성 자산 기말잔액 − 기초잔액`.
- **일별 보고서(`date=D`)**:
  - D 기준 재무상태표 스냅샷 + D 의 현금흐름 단일일 발췌 + D 분개 목록.

### 4.7 시스템 기본 계정 시드 (일반 범위)

가입 승인 시 `PENDING → ACTIVE` 전환 직후 사용자별로 아래 기본 계정 생성. 사용자는 이후 자유롭게 추가/이름변경 가능.

- **ASSET**: 현금, 주거래은행(placeholder), 증권계좌(placeholder)
- **LIABILITY**: 신용카드(placeholder), 대출(placeholder)
- **EQUITY**: 자본금(사용자 개업자본), 이익잉여금(자동 집계 대상)
- **INCOME (cash_flow_section)**:
  - OPERATING: 급여, 상여금, 사업소득, 이자수입, 기타수입
  - INVESTING: 배당수입, 투자수익(실현), 예적금 해지수입
  - FINANCING: 대출수령
- **EXPENSE (cash_flow_section)**:
  - OPERATING: 식비, 주거비, 교통비, 통신비, 공과금, 의료비, 교육비, 의류·미용, 여가·문화, 기타지출
  - INVESTING: 예적금 납입, 주식매수 (※ 자산 취득은 원칙상 ASSET 계정으로 처리, EXPENSE 가 아님 — 시드는 단순 "투자성 지출 기록용" 보조로만 제공하며 기본 비활성)
  - FINANCING: 대출상환 이자 (원금 상환은 LIABILITY 감소로 처리)

> 투자/재무 거래의 원칙: **원금 이동은 자산/부채 계정 간 이동** 으로만 기록, **손익은 INCOME/EXPENSE** 로 기록. Application 레이어에 이 규칙을 단위 테스트로 박제한다.

---

## 5. REST API 설계 (요약)

### Auth
- `POST /api/auth/register` — 이메일/비밀번호/표시명
- `POST /api/auth/login` — access + refresh 토큰 발급
- `POST /api/auth/refresh`
- `POST /api/auth/logout`
- `GET  /api/me`

### Transactions (일반 사용자)
- `GET    /api/transactions?from=&to=&section=`
- `POST   /api/transactions`
- `PATCH  /api/transactions/{id}`
- `DELETE /api/transactions/{id}`

### Accounts (원장 계정 CRUD — INCOME/EXPENSE 계정이 곧 "카테고리")
- `GET    /api/accounts`
- `POST   /api/accounts`
- `PATCH  /api/accounts/{id}`
- `DELETE /api/accounts/{id}` (soft delete)

### Journal Entries (사용자 UX 레이어)
- `POST /api/entries` — body: `{ occurred_on, amount, payment_method, expense_or_income_account_id, counter_account_id?, merchant, memo }`. 서버가 복식부기 라인 2개 이상 구성.
- `GET  /api/entries?from=&to=` / `PATCH /api/entries/{id}` / `DELETE /api/entries/{id}` (soft)

### Reports
- `GET /api/reports/cash-flow?from=YYYY-MM-DD&to=YYYY-MM-DD`
- `GET /api/reports/balance-sheet?on=YYYY-MM-DD`
- `GET /api/reports/daily?date=YYYY-MM-DD` — 그날의 재무상태표 스냅샷 + 당일 거래

### Admin
- `GET   /api/admin/users`
- `PATCH /api/admin/users/{id}` — role/status 변경
- `DELETE /api/admin/users/{id}` — soft delete

모든 `/api/*` (auth 제외) 는 JWT 미들웨어, `/api/admin/*` 는 ADMIN 역할 미들웨어.

---

## 6. 프론트엔드 화면

| 경로 | 설명 |
| --- | --- |
| `/login`, `/register` | 인증 |
| `/dashboard` | 오늘의 자산/부채/순자산 + 이번 달 현금흐름 요약 |
| `/transactions` | 거래 목록/입력/수정/삭제. 일별 그룹 표시 |
| `/reports/cash-flow` | 기간 선택 → 영업/투자/재무 3구간 현금흐름표 |
| `/reports/balance-sheet` | 기준일 선택 → 자산/부채/자본 표 |
| `/admin/users` | ADMIN 전용 회원 관리 |

---

## 7. 구현 단계 (TDD 사이클 기반)

각 단계는 **Red → Green → Refactor** 순서 엄수. 세부 규칙은 `CLAUDE.md` 참조.

### Phase 0 — 하네스 부트스트랩
1. 디렉토리 구조 생성, `docker-compose.yml`, Dockerfile
2. Backend: `composer init`, Slim 4, PHPUnit, PHPStan, CS-Fixer, 기본 헬스체크 엔드포인트 `GET /api/health` 에 대한 **첫 테스트(Red) → Green** 까지.
3. Frontend: `create-next-app`, TS strict, Vitest, RTL, Playwright, ESLint/Prettier. 기본 `/` 페이지 스모크 테스트.
4. DB: 빈 `budget_book` 스키마, 테스트용 `budget_book_test` 스키마 생성.
5. 루트 `Makefile` (또는 `just` 파일): `make test`, `make lint`, `make typecheck`, `make e2e`.

### Phase 1 — 인증 도메인
1. `users` 마이그레이션 + 테스트
2. `RegisterUser` 유스케이스 (도메인 테스트)
3. `POST /api/auth/register` Feature 테스트
4. `LoginUser` + JWT 발급 (Feature 테스트)
5. JwtAuth 미들웨어 + `GET /api/me`
6. Frontend: 로그인/회원가입 폼 (Vitest 컴포넌트 테스트 → 구현 → Playwright 로그인 플로우)

### Phase 2 — 원장(계정과목)
1. `accounts` 마이그레이션 (5 계정과목 + subtype + normal_balance + cash_flow_section)
2. **계정 활성화 시드 서비스**: 관리자가 사용자 승인(`PENDING → ACTIVE`) 시 기본 계정 세트 자동 생성 (Unit + Integration test).
3. Account CRUD 유스케이스 + Feature 테스트
4. Frontend 계정 관리 페이지

### Phase 3 — 분개 입력 (핵심, 완전 복식부기)
1. `journal_entries`, `journal_entry_lines` 마이그레이션 (CHECK 제약 포함)
2. 도메인 불변식: `JournalEntry` 집계 루트가 **항상 차대 균형**을 보장 — 생성자/팩토리에서 검증, Unit test 로 박제.
3. `RecordJournalEntry` 유스케이스 — 사용자 입력 → 2개 라인 자동 구성 (Unit: 카드/현금/이체/투자/재무 시나리오 전부).
4. `GET/PATCH/DELETE /api/entries` Feature 테스트 (soft delete 포함).
5. Frontend 거래 입력 폼 + 일별 그룹 목록.

### Phase 4 — 보고서
1. **재무상태표 서비스** (Unit): `자산 = 부채 + 자본` 항등식 assert, 기준일 스냅샷.
2. **현금흐름표 서비스** (Unit): 직접법, `Σ(3구간) = 현금성 자산 증감` assert.
3. 일별 보고서 (재무상태표 스냅샷 + 당일 분개) Feature 테스트.
4. `/api/reports/*` Feature 테스트.
5. Frontend 대시보드, 현금흐름표/재무상태표 페이지.

### Phase 5 — 관리자
1. Admin 미들웨어 단위 테스트
2. `/api/admin/users` CRUD Feature 테스트
3. Frontend 관리자 회원 목록/수정

### Phase 6 — 마무리
1. E2E 시나리오: 가입 → 로그인 → 거래 입력 → 보고서 확인 → 관리자 회원 승인
2. 성능/인덱스 점검 (`transactions(user_id, occurred_on)` 인덱스)
3. README 완성

---

## 8. 하네스 / 품질 게이트 (요약)

세부 규칙은 `CLAUDE.md` 의 **TDD Harness** 섹션에서 강제한다. 핵심만:

- 테스트를 **반드시 먼저** 작성한다 (실패 확인 → 구현 → 통과 → 리팩터).
- `make test` / `make lint` / `make typecheck` 전부 통과하지 않으면 커밋 금지.
- PHPStan level 8, ESLint error 0, PHPUnit & Vitest 전부 녹색.
- 하나의 기능 = 최소 **단위 + 통합/Feature** 두 계층 테스트.
- Frontend 주요 플로우는 Playwright E2E 1개 이상.

---

## 9. 확정된 결정사항 (사용자 승인 완료)

아래 항목은 사용자 확인으로 **확정**되었으며 본 계획 전체에 반영되었다.

1. **복식부기**: **완전 복식부기** 채택 (journal entry + balanced lines). §4 스키마/§7 Phase 3 반영.
2. **통화**: **원화(KRW)만**. 스키마에 통화 필드를 두지 않는다. 추후 다통화 전환이 필요하면 별도 마이그레이션.
3. **기본 계정 시드**: §4.7 일반 범위로 확정.
4. **가입 방식**: 셀프 가입 후 **관리자 승인(`PENDING → ACTIVE`)** 방식. 승인 시 §4.7 기본 계정 세트 자동 생성.
5. **삭제 정책**: **Soft delete**. `deleted_at` 컬럼으로 처리. 모든 목록 쿼리는 `WHERE deleted_at IS NULL` 기본 적용.

---

## 10. 작업 시작 전 확인

**이 PLAN.md + CLAUDE.md 내용을 검토 후 "진행해" 라고 답해주시면 Phase 0부터 TDD로 착수합니다.** 승인 이후에는 별도 확인 없이 Phase 0 → Phase 6 순서로 진행하며, 범위 밖 스택/결정 변경이 필요한 경우에만 중간에 재확인한다.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

본 문서는 **웹 기반 가계부(Budget Book)** 프로젝트의 **기술 헌법(Technical Constitution)** 이자 **TDD 하네스(Harness) 규칙**이다. 본 문서와 `PLAN.md` 는 모든 구현 작업의 상위 규범이며, 충돌 시 **CLAUDE.md 가 우선**한다.

---

## 0. Project Snapshot

- **Product**: 현금흐름표 / 재무상태표 형식을 반영한 개인 웹 가계부 (다중 사용자 + 관리자)
- **Repo layout**: `backend/` (PHP API), `frontend/` (Next.js), `docker-compose.yml` 로 로컬 환경 구동
- **Working directory**: `/home/k1410857/claude-workspace/budget-book`
- **상세 계획**: [`PLAN.md`](./PLAN.md)

---

## 1. 기술 스택 제약 (변경 금지)

아래 스택은 **사용자 승인 없이 변경 불가**. 다른 라이브러리/프레임워크로 대체하거나, 아래에 없는 중대 의존성을 추가하려면 반드시 사용자에게 확인한다.

| Layer | 고정 스택 |
| --- | --- |
| Language (BE) | **PHP 8.4** |
| BE Framework | **Slim Framework 4** + PHP-DI |
| BE ORM | **Eloquent** (`illuminate/database`) |
| BE Migration | **Phinx** |
| BE Auth | `firebase/php-jwt` (JWT) + `password_hash` (bcrypt) |
| BE Test | **PHPUnit 11** |
| BE 정적분석 | **PHPStan level 8**, **PHP-CS-Fixer** (PSR-12) |
| Language (FE) | **TypeScript (strict)** |
| FE Framework | **Next.js 15 (App Router)** |
| FE 서버상태 | **TanStack Query v5** |
| FE UI 상태 | **Zustand** |
| FE 폼/검증 | **React Hook Form + Zod** |
| FE 스타일 | **Tailwind CSS** |
| FE 단위/컴포넌트 테스트 | **Vitest + React Testing Library** |
| FE E2E | **Playwright** |
| FE Lint/Format | **ESLint (flat config)** + **Prettier** |
| DB | **MySQL 8** (`utf8mb4_0900_ai_ci`) |
| 로컬 실행 | **Docker Compose** |
| Node | **20 LTS 이상** |

### 금지 사항 (hard rules)
- **다른 백엔드 프레임워크 금지**: Laravel full framework, Symfony full stack, CodeIgniter 등 도입 금지. (Laravel 구성요소 중 `illuminate/database` 만 Eloquent 용도로 허용.)
- **다른 런타임 금지**: Python/Go/Node 백엔드 금지.
- **ORM 교체 금지**: Doctrine 등으로 교체 금지.
- **CSS 프레임워크 추가 금지**: Bootstrap, Material-UI 등 추가 금지 (Tailwind + headless 컴포넌트만).
- **테스트 러너 교체 금지**: Pest/Jest 등 다른 러너로 대체 금지.
- **복식부기 의미론 변경 금지**: 모든 거래는 `journal_entries` + `journal_entry_lines` 2개 이상 라인으로 저장되며, **분개별 차변 합계 = 대변 합계** 를 반드시 만족한다. 단식부기 저장으로 회귀 금지.

---

## 2. 디렉토리 규약

```
backend/  src/Domain | src/Application | src/Infrastructure | src/Interface/Http
          tests/Unit | tests/Integration | tests/Feature
frontend/ app/ | components/ | lib/ | tests/unit | tests/e2e
```

- **Domain 레이어는 프레임워크/DB/HTTP에 의존하지 않는다**. PHP 순수 객체만 허용.
- **Application 레이어**는 Domain 에만 의존. Repository 는 인터페이스로 주입.
- **Infrastructure** 에서 Eloquent, PDO, JWT 등 구현.
- **Interface/Http** 는 Slim 라우트/컨트롤러/미들웨어 전용.
- Frontend `app/` 하위에는 **route segment** 만, 재사용 로직은 `components/` 또는 `lib/`.

---

## 3. TDD Harness — 절대 규칙

> **"테스트가 없으면 구현도 없다."** 모든 프로덕션 코드는 그 코드를 필요로 하는 실패 테스트에서 태어난다.

### 3.1 Red → Green → Refactor (엄수)

모든 기능/버그픽스에 대해 아래 사이클을 **단계마다 사용자에게 증빙 가능**해야 한다:

1. **RED**
   - 해당 기능에 대한 **실패하는 테스트**를 먼저 작성한다.
   - `make test-be` 또는 `make test-fe` 로 **실행하여 실패 출력을 확인**한다. (단순 "작성했음" 으로 끝내지 않는다.)
   - 커밋 메시지 prefix: `test(<scope>): add failing test for <behavior>` (선택적으로 별도 커밋, 또는 다음 Green 커밋과 묶어도 됨).

2. **GREEN**
   - **테스트를 통과시키는 최소한의 코드**만 작성한다. 불필요한 기능 추가 금지.
   - 전체 테스트 스위트가 녹색인지 확인.

3. **REFACTOR**
   - 중복 제거, 네이밍 개선, 책임 분리. **테스트는 추가하지 않고** 기존 테스트가 계속 녹색이어야 한다.
   - 이 단계에서 구조 변경 후 `make lint && make typecheck` 통과 필수.

### 3.2 테스트 계층 필수 조합

모든 **기능(feature)** 은 아래 중 **최소 2개 계층** 의 테스트를 가진다. 도메인 로직이 있는 기능은 **반드시 Unit** 포함.

| 계층 | 도구 | 대상 |
| --- | --- | --- |
| Unit (BE) | PHPUnit | Domain/Application 순수 로직. DB/HTTP 금지. |
| Integration (BE) | PHPUnit + MySQL test DB | Repository, Migration, Query |
| Feature (BE) | PHPUnit + Slim App (PSR-7) | HTTP 요청→응답 전체 |
| Unit/Component (FE) | Vitest + RTL | 컴포넌트, hook, zod schema |
| E2E (FE) | Playwright | 사용자 플로우 |

**규칙**: 새 API 엔드포인트 → Feature 테스트 필수. 새 도메인 규칙 → Unit 테스트 필수. 새 DB 쿼리 → Integration 테스트 필수. 새 화면 플로우 → Playwright 시나리오 1개 이상.

### 3.3 커버리지 게이트

- Backend `phpunit.xml` 에 coverage 설정. **`src/Domain`, `src/Application` 라인 커버리지 ≥ 90%**, 전체 ≥ 80%.
- Frontend `vitest.config.ts` 에 v8 coverage. **`lib/` ≥ 85%**, `components/` ≥ 70%.
- 기준 미달 시 CI/로컬 게이트 실패 → 커밋 금지.

### 3.4 테스트 네이밍 & 구조

- **BE**: `ClassNameTest.php`, 메서드명은 `it_should_<behavior>()` 또는 `test_<behavior>()`. AAA(Arrange-Act-Assert) 주석 권장.
- **FE**: `<Name>.test.ts(x)` 또는 `<name>.spec.ts`. `describe` 블록은 모듈/컴포넌트명. `it('should …')`.
- **E2E**: `tests/e2e/<flow>.spec.ts`. 시나리오 한 개당 한 파일.

### 3.5 테스트 격리

- Integration/Feature 테스트는 **트랜잭션 롤백** 또는 **테스트별 스키마 초기화**로 서로 간섭하지 않는다.
- 고정 시간이 필요한 테스트는 **Clock 추상화**(`Domain\Clock` 인터페이스)를 주입하고 테스트에서 고정값을 사용한다. `new DateTime('now')` 직접 호출 금지.
- 난수/UUID 도 동일 원칙 (`IdGenerator` 인터페이스).

### 3.6 Mock 사용 원칙

- **Repository 인터페이스** 는 Application 테스트에서 가짜 구현(in-memory) 으로 대체해도 된다.
- **DB** 는 Integration/Feature 에서 **실 MySQL** 을 쓴다. SQLite 대체 금지 (MySQL 전용 기능 사용 예정).
- **HTTP 외부 호출** 은 원칙상 없음. 필요 시 wrapper 인터페이스를 두고 mock.

### 3.7 진행 중 작업(WIP) 규칙

- **Red 상태로 커밋하지 않는다.** 실패 테스트는 구현과 함께 같은 작업 단위에서 Green으로 가야 한다.
- 기능이 커서 여러 커밋이 필요하다면, 각 커밋이 **자체적으로 Green** 이어야 한다 (기능의 부분집합 + 해당 부분의 테스트).
- `@skip`, `markTestSkipped`, `it.skip` 은 **사유 주석 필수**이며 24시간 이상 유지 금지.

---

## 4. 필수 명령어 (Makefile 타깃)

루트 `Makefile` 이 아래 타깃을 제공해야 한다. 구현 전 Phase 0 에서 작성.

| 명령 | 동작 |
| --- | --- |
| `make up` | Docker Compose 기동 (mysql, php-fpm, nginx, node) |
| `make down` | 컨테이너 정지 |
| `make install` | `composer install` + `npm ci` |
| `make migrate` | Phinx 마이그레이션 (개발 DB) |
| `make migrate-test` | 테스트 DB 마이그레이션 |
| `make seed` | 시스템 기본 카테고리 시드 |
| `make test` | **`test-be` + `test-fe` 전부** |
| `make test-be` | `phpunit` (coverage 포함) |
| `make test-fe` | `vitest run --coverage` |
| `make test-unit` | BE Unit + FE Unit 만 |
| `make e2e` | Playwright 실행 (앱 기동 후) |
| `make lint` | `php-cs-fixer --dry-run` + `eslint` |
| `make fmt` | `php-cs-fixer fix` + `prettier --write` |
| `make typecheck` | `phpstan analyse` + `tsc --noEmit` |
| `make ci` | `lint && typecheck && test` (커밋 직전 필수) |

### 단일 테스트 실행

- BE: `backend$ vendor/bin/phpunit --filter 'it_should_reject_invalid_email' tests/Unit/Account/RegisterUserTest.php`
- FE: `frontend$ npx vitest run tests/unit/LoginForm.test.tsx -t "invalid email"`
- E2E: `frontend$ npx playwright test tests/e2e/login.spec.ts --project=chromium`

---

## 5. 품질 게이트 (커밋/PR 직전)

커밋 전 **반드시** `make ci` 가 통과해야 한다. 구성 요소:

1. `php-cs-fixer --dry-run --diff` → 위반 0
2. `phpstan analyse --level=8` → 오류 0
3. `phpunit` → 전부 Green, 커버리지 게이트 통과
4. `eslint .` → error 0 (warning 은 허용하되 신규 warning 금지)
5. `tsc --noEmit` → 오류 0
6. `vitest run --coverage` → 전부 Green, 커버리지 게이트 통과
7. (주요 플로우 변경 시) `playwright test` → Green

게이트를 우회하는 `--no-verify`, 테스트 스킵, `as any`, `@ts-ignore`, `@SuppressWarnings` 사용 **금지**. 정말 필요한 경우 주석으로 사유 + 만료 조건 명시.

---

## 6. 보안/운영 규칙

- 비밀번호는 `password_hash(…, PASSWORD_BCRYPT, ['cost' => 12])` 이상.
- JWT 비밀키, DB 자격증명은 **`.env`** 로만 관리. `.env` 는 커밋 금지, `.env.example` 제공.
- SQL 은 Eloquent/PDO **prepared statement** 만 사용. 문자열 concat 금지.
- 금액은 **`DECIMAL(18,2)`** + PHP 측 `brick/math` 또는 정수(최소 단위=원) 로 계산. `float` 로 금액 계산 금지.
- 모든 API 입력은 **Zod(FE) / 전용 Validator(BE)** 로 검증 후 Application 레이어 진입.
- CORS 는 `frontend` origin 만 허용.

---

## 7. 회계 도메인 규칙 (코드 작성 시 준수)

### 7.1 복식부기 불변식 (항상 참)

- 모든 사용자 거래는 `JournalEntry` 집계 루트를 통해 저장되며, **라인 2개 이상** + **Σ(debit) = Σ(credit)** 을 만족한다.
- `JournalEntry::record(...)` 팩토리는 불균형 입력에 대해 **예외**를 던진다. 이 불변식은 **Domain Unit test 에 박제**되어야 한다.
- 각 라인은 `debit` 또는 `credit` **중 정확히 하나만 > 0** (양쪽 0 또는 양쪽 >0 금지).
- `transactions` 테이블을 신규로 만들지 않는다. 기존 문서의 `transactions` 언급은 `journal_entries` 로 해석.

### 7.2 계정과목(5 원장)

- 계정과목은 `ASSET / LIABILITY / EQUITY / INCOME / EXPENSE` 로만 분류.
- 정상잔액(normal_balance): `ASSET/EXPENSE = DEBIT`, `LIABILITY/EQUITY/INCOME = CREDIT`.
- 계정 잔액은 **파생 계산**(분개 집계) 을 원칙. 잔액 캐시 테이블/컬럼 금지 (Phase 6 성능 이슈 시 재논의).
- **원금 이동은 자산/부채 계정 간**, **손익은 INCOME/EXPENSE 계정으로**. (예: 대출 원금 상환은 LIABILITY 감소 + ASSET 감소; 대출 이자는 EXPENSE 증가 + ASSET 감소.) 이 규칙은 Application unit test 로 검증.

### 7.3 현금흐름표 (직접법)

- 섹션: `OPERATING / INVESTING / FINANCING`. 각 INCOME/EXPENSE/일부 ASSET·LIABILITY 계정은 `cash_flow_section` 을 가진다.
- 기간 내 **현금성 계정(ASSET subtype ∈ {CASH,BANK})** 이 관여한 라인의 상대편 라인을 기준으로 섹션 집계.
- 검증 assert (테스트 + 런타임): `Σ(3섹션 순증감) = 기말 현금성자산 − 기초 현금성자산`.

### 7.4 재무상태표

- 기준일 `D` 스냅샷 = `opening_balance + Σ(D 까지 차변 − 대변)` (normal_balance 부호로 정규화).
- 항등식 `자산 = 부채 + 자본(+ 당기순이익)` 을 테스트에서 assert.

### 7.5 섹션 매핑 / 시드 규칙

- 계정 → 현금흐름 섹션 매핑은 **DB (`accounts.cash_flow_section`)** 에 저장. 코드 하드코딩 금지.
- 시스템 기본 계정 시드는 사용자 승인(`PENDING → ACTIVE`) 트랜잭션 내에서 함께 생성. 이 과정은 **Integration test 로 보장**.

### 7.6 사용자/관리자 권한

- 가입 직후 `users.status = PENDING`. ADMIN 이 `ACTIVE` 로 전환해야 로그인 후 실사용 가능 (단, `/api/auth/login` 은 PENDING 사용자에게 "승인 대기" 응답).
- `ADMIN` 은 `/api/admin/*` 에만 추가 권한. 일반 API 에서는 ADMIN 도 자기 데이터만 본다.
- 모든 삭제는 **soft delete** (`deleted_at`). 하드 삭제 쿼리 금지. 목록 쿼리 기본은 `WHERE deleted_at IS NULL`.

### 7.7 금액/통화

- 통화는 **KRW 고정**. 스키마에 `currency` 컬럼을 두지 않는다.
- 금액은 `DECIMAL(18,2)` + PHP 측 `brick/math` (BigDecimal) 로 계산. `float` 금지 (§6 과 중복 강조).

---

## 8. 코딩 스타일 요약

- PHP: PSR-12, `declare(strict_types=1);` 모든 파일 최상단, 반환타입 명시, `readonly` 프로퍼티 선호.
- TS: strict, `noUncheckedIndexedAccess` 활성, `any` 금지, 모든 API 응답은 Zod 스키마로 파싱.
- React: 서버 컴포넌트 우선, 클라이언트 경계 최소화. 상태관리는 TanStack Query (서버) / Zustand (UI) 로만.
- 파일당 한 개 공개 클래스/컴포넌트.
- 주석은 **왜(Why)** 만 기록. **무엇(What)** 은 네이밍으로.

---

## 9. 작업 절차 (Claude 용 체크리스트)

새 기능/수정 착수 시 이 순서를 따른다:

1. `PLAN.md` 의 해당 Phase 확인 → 범위 일치 여부 검증.
2. 필요한 테스트 파일을 **먼저** 만든다 (Red).
3. `make test-be` / `make test-fe` 로 Red 확인.
4. 최소 구현 작성 → Green 확인.
5. Refactor 후 `make ci` 전체 통과.
6. 한 Phase 완료 시 사용자에게 **요약 보고** (커밋 목록, 테스트 수, 커버리지, 남은 작업).

### 금지된 행동

- 테스트 없이 "일단 만들고 나중에 테스트" 작성. → **금지.**
- 실패하는 테스트를 `skip` 으로 우회. → **금지.**
- 스택 변경/라이브러리 추가를 **자체 판단**으로 수행. → **금지. 사용자 확인 필수.**
- 사용자 거래를 **단일 라인**으로 저장하거나 **차대 불균형** 분개를 허용. → **금지.**
- 거래 테이블을 `transactions` 이름으로 신규 생성. → **금지.** `journal_entries` + `journal_entry_lines` 사용.
- **하드 삭제**(`DELETE FROM ...`) 쿼리 작성. → **금지.** soft delete 만 사용.
- 금액을 `float` 로 다루기. → **금지.**
- 스키마에 `currency` 컬럼 추가. → **금지.** (KRW 고정, 추후 재논의 시 별도 마이그레이션)
- `frontend` 에서 `fetch` 를 원시로 사용. → `lib/api/client.ts` 래퍼를 경유.

---

## 10. 초기 부트스트랩 승인 게이트

본 저장소는 아직 **비어 있는 상태**이다. `PLAN.md §9` 의 5개 결정은 **확정 완료** (완전 복식부기 / KRW 고정 / 일반 범위 시드 / 관리자 승인 가입 / soft delete). Phase 0 (스캐폴딩 + 첫 Red/Green 사이클) 착수는 사용자의 **명시적 승인("진행해" 등)** 을 기다린다. 승인 이후에는 Phase 0 → Phase 6 순서로 본 문서의 TDD 규칙에 따라 진행한다.

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

Phase 0 → Phase 7 은 **완료 상태**(2026-04-22). 추가 작업 착수 시에도 본 문서의 TDD 규칙과 아래 §11 "반복 오류 방지" 를 우선한다. `PLAN.md §9` 의 5개 결정은 **확정** (완전 복식부기 / KRW 고정 / 일반 범위 시드 / 관리자 승인 가입 / soft delete).

**Phase 7 — 닷홈(dothome.co.kr) 배포 타깃** (2026-04-22 완료)
- **same-origin** 구조로 합침: 프론트(Next.js static export) + 백엔드(Slim/PHP) 를 한 docroot(`public_html/`)에 배치.
- `make dothome-bundle` 로 `deploy/samdogs-bundle.zip` 생성 → FTP 업로드 → `/api/precheck.php` → `/api/install.php` → 두 파일 삭제.
- `BUILD_TARGET=dothome` 환경변수로 Next.js `output: 'export'` 조건부 활성. 로컬 dev (SSR 모드)는 그대로 유지.
- `CORS_ALLOWED_ORIGIN=same-origin` 또는 빈값이면 CORS 미들웨어 자체를 미등록 (same-origin 에서는 preflight 가 발생하지 않음).
- 설치 흐름은 `backend/src/Deploy/{PrecheckRunner, Installer, PhinxMigrator}` + `backend/deploy/entries/*.php` 엔트리로 구현. 상세 절차는 [`docs/DEPLOY_DOTHOME.md`](./docs/DEPLOY_DOTHOME.md).

---

## 11. 반복 오류 방지 — Lessons learned

Phase 0~6 구현 중 반복적으로 막혔던 함정들. 새 코드를 작성할 때 **사전에 회피**하고, 수정 시 이 섹션을 먼저 참조한다.

### 11.1 PHP / PHPStan level 8

- **PHP 8.4 implicit nullable 금지**: 파라미터 기본값이 `null` 인 경우 타입 앞에 `?` 를 반드시 붙인다.
  ```php
  // 금지 (PHP 8.4 deprecation 경고)
  protected function request(string $method, array $body = null) {}
  // 올바름
  protected function request(string $method, ?array $body = null) {}
  ```
- **Slim 제네릭 타입 명시**: `public static function create(): App` 은 PHPStan level 8 에서 실패. 반환타입에 `App<\Psr\Container\ContainerInterface|null>` 을 docblock 으로 지정한다.
- **`isset($x) && $x !== null` 중복 체크 금지**: `isset()` 이 이미 null 을 배제하므로 PHPStan 은 `!== null` 을 `alwaysTrue` 로 처리한다. `isset($x)` 단독 사용.
- **`match` 마지막 arm `alwaysTrue` 경고**: 앞서 `if` 로 일부 enum 값을 걸러내고 `match` 에서 다시 그 값을 arm 으로 쓰면 PHPStan 이 경고한다. 해결책 (선호 순):
  1. `default => null` arm 추가
  2. `if/elseif/else` 체인으로 재작성
  3. 상단 필터를 제거하고 `match` 에서 모두 처리
- **Eloquent Query Builder row 접근**: `$row->column` 직접 접근은 PHPStan 이 `object::$column` 으로 읽어 "property not found" 오류. `(array) $row` 로 캐스팅 후 `$data['column'] ?? default` 로 접근한다. hydrate 함수의 인자 타입은 `?object` 가 좋다 (`?stdClass` 는 pluck 결과 타입과 충돌).
- **Repository `list<X>` 반환**: `Builder->pluck()->map()->all()` 은 key 보존 `array<int,X>` 를 반환. `list<X>` 로 선언했다면 `array_values(...)` 로 재인덱스.
- **PHPStan 메모리**: level 8 전체 분석에 128MB 부족. `phpstan analyse --memory-limit=1G` (이미 `make typecheck-be` 에 반영). 개별 실행 시 잊지 말 것.

### 11.2 Firebase JWT + Clock 추상화

- **JWT 발행과 검증의 시계 불일치**: `JWT::encode` 는 `iat/exp` 를 호출자가 제공하지만 `JWT::decode` 는 **시스템 시계**(`time()`) 로 만료를 검사. 테스트에서 `FixedClock` 으로 토큰을 발행하면 실제 현재 시각과 차이가 나서 `ExpiredException` 이 임의로 터진다. **해결**: `verifyAccess()` 에서 `JWT::$timestamp = $this->clock->now()->getTimestamp();` 를 일시 설정하고 `finally` 에서 복원.

### 11.3 Zustand persist (Next.js)

- **SSR 에서 `persist.hasHydrated()` 호출 금지**: Next.js 서버 사이드 렌더 중 `useAuthStore.persist` 가 `undefined` 일 수 있다. `useState` 초기값 함수 안에서 바로 호출하면 SSR 500 오류.
- **보호된 페이지 가드 패턴**: 모든 `/dashboard`, `/accounts`, `/transactions`, `/reports/*`, `/admin/*` 는 `useAuthHydrated()` (lib/stores/auth.ts) 훅으로 hydration 완료 여부를 기다린 후에야 redirect 판단을 내린다. 패턴:
  ```tsx
  const hydrated = useAuthHydrated();
  const token = useAuthStore((s) => s.accessToken);
  useEffect(() => {
    if (!hydrated) return;
    if (!token) { router.replace('/login'); return; }
    void reload();
  }, [hydrated, token, router, reload]);
  if (!hydrated) return null;
  if (!token) return null;
  ```
  이 가드 없이 바로 redirect 하면 rehydration 완료 전 login 으로 튕겨 `/login` 이 렌더된다.

### 11.4 Playwright E2E

- **`page.waitForResponse` race**: 초기 렌더에서 이미 GET 이 발생한 뒤 `waitForResponse(GET)` 를 등록하면 다음 GET 이 없어서 타임아웃. 패턴:
  ```ts
  await Promise.all([
    page.waitForResponse((res) => POST 필터),
    page.waitForResponse((res) => GET 필터),
    page.getByRole('button', { name: /submit/ }).click(),
  ]);
  ```
  클릭 직전에 모든 watcher 를 등록하면 race 가 없어진다.
- **`getByText` 중복**: 같은 값(예: 금액 `-12000.00`)이 헤더·항목·요약에 여러 번 노출되면 strict-mode 위반. `.first()` 또는 `.nth(n)` 또는 더 좁은 locator 사용.
- **Date input `fill` 전 clear**: `userEvent.type(dateInput, '2026-04-22')` 는 기존 값 뒤에 **append** 되어 형식이 깨진다. Playwright `page.locator(...).fill(...)` 은 덮어쓰므로 이쪽을 선호. Vitest + RTL 환경에서 `user.type` 을 써야 할 경우 `await user.clear(input); await user.type(input, value);`.
- **로컬스토리지 주입은 addInitScript로**: 토큰 prefill 은 첫 navigation 전 `page.addInitScript((t) => window.localStorage.setItem('bb-auth', JSON.stringify({...})))` 로. 네비게이션 뒤 `page.evaluate` 는 너무 늦거나 race 소지.
- **병렬 작업자 주의**: 공유 MySQL 에 여러 워커가 쓰면 admin 승인 같은 동일 리소스 경합. 개발 중엔 `--workers=1` 로 돌린다. CI 에서는 각 테스트가 독립 이메일/리소스를 만들도록.

### 11.5 MySQL / Phinx

- **MySQL 8 CHECK 제약**: Phinx 테이블 API 에는 CHECK 가 없다. `addColumn` 후 **`$this->execute("ALTER TABLE ... ADD CONSTRAINT ... CHECK (...)")`** 로 붙인다. Integration test 에서 PDO 예외로 검증.
- **인덱스 커버리지 회귀 방지**: 새 쿼리 패턴을 추가하면 `tests/Integration/Migration/IndexCoverageTest.php` 에 EXPLAIN 어설션을 하나 더 넣어 `type != 'ALL'` 를 강제한다.
- **docker-compose 환경변수 전달**: `.env` 자동 상속이 아니라 `docker-compose.yml` 의 서비스 `environment:` 블록에 **명시적으로 매핑**해야 컨테이너에서 읽힌다 (예: `INITIAL_ADMIN_EMAIL`, `CORS_ALLOWED_ORIGIN`).

### 11.6 금액 포맷 일관성

- **POST 응답 vs GET 응답 스케일 차이**: 유스케이스가 만든 `BigDecimal::of('8000')` 은 scale 0 → `"8000"`. DB 에서 읽은 `DECIMAL(18,2)` 는 scale 2 → `"8000.00"`. Presenter 가 달라진 값을 내보내면 프론트 Zod 일관성/E2E 어설션이 깨진다. **해결**: Presenter 에서 `$amount->toScale(2, RoundingMode::HALF_UP)` 로 항상 정규화 (`JournalEntryPresenter` 참조).

### 11.7 Slim 미들웨어 순서

- **CorsMiddleware 는 라우팅 바깥에**: OPTIONS preflight 가 routing 에서 404 로 끝나지 않도록 **`addRoutingMiddleware` 다음, `addErrorMiddleware` 이전** 에 `$app->add(new CorsMiddleware(...))` 를 넣는다. 순서는 LIFO (가장 나중에 `add` 한 미들웨어가 가장 바깥).

### 11.8 복식부기 개시 잔액

- **BalanceSheet identity 실패 주의**: 사용자가 `opening_balance > 0` 인 ASSET 계정만 가지고 있고 이를 offsetting 하는 EQUITY/LIABILITY opening 이 없으면 `자산 = 부채 + 자본` 이 깨진다. `BalanceSheetService` 는 `개시자본(초기 잔액)` synthetic equity 라인을 자동으로 덧붙여 항등식을 유지한다. 이 보정 로직을 건드릴 때는 `BalanceSheetServiceTest` 의 `test_per_account_balances_exposed` + `test_opening_balance_only_satisfies_identity` 를 먼저 업데이트.

### 11.9 분개 수정 (PATCH)

- **수정 = 새 분개 기록 + 기존 소프트 삭제** 순서. 반대로 하면 새 분개 검증이 실패할 때 원본이 이미 삭제돼 데이터가 유실된다. `UpdateJournalEntry` 참조. 어느 경우든 `JournalEntry::record()` 를 다시 거쳐 차대 균형 불변식이 강제되는지 확인.

### 11.10 닷홈/FTP-only 배포 타깃 (Phase 7)

- **Next.js static export 전제조건**: 모든 route 가 `'use client'` + `cookies()/headers()` 미사용 + Server Actions 미사용 + dynamic route 는 `generateStaticParams` 필수. 하나라도 위반하면 `next build` 가 실패하거나 export 출력이 불완전해진다. 새 페이지 추가 시 이 조건을 지켜야 닷홈 배포가 유지된다.
- **API base URL 기본값은 상대경로 `/api`**: `NEXT_PUBLIC_API_BASE` 가 비어 있을 때 fallback 은 반드시 `/api` (same-origin). 로컬 dev 는 `frontend/.env.local` 에서 `http://localhost:8080/api` 오버라이드. 과거 기본값 `http://localhost:8080/api` 로 되돌리면 닷홈 빌드가 잘못된 origin 으로 fetch 한다.
- **CORS 조건부 등록**: `AppFactory` 에서 `CORS_ALLOWED_ORIGIN` 이 `''` 또는 `'same-origin'` 이면 `CorsMiddleware` 를 **등록하지 않는다**. same-origin 배포에서는 preflight 자체가 없어 문제없고, 미들웨어를 남기면 Access-Control-Allow-Origin 헤더가 잘못 나간다.
- **Phinx programmatic 실행**: CLI 없는 호스팅에서 `install.php` 가 마이그레이션을 돌려야 한다. `new \Phinx\Wrapper\TextWrapper(new \Phinx\Console\PhinxApplication(), ['configuration' => ..., 'environment' => 'development'])` → `getMigrate()` → `getExitCode()`. `PhinxMigrator` 어댑터 참고.
- **설치 sentinel + 1회 실행 가드**: `install.php` 는 `public_html/.installed` 파일 존재 시 403 으로 차단. 재설치가 필요하면 DB 를 비우고 해당 파일을 지운다. `precheck.php/install.php` 는 설치 후 **반드시 FTP 에서 삭제** (보안 체크리스트 항목).
- **Apache `.htaccess` 3단 구조**: docroot `.htaccess` 는 `/api/*` → `api/index.php` + SPA fallback, `api/.htaccess` 는 `.env/.lock/.md` 숨김파일 차단, `app/.htaccess` 는 `Require all denied` (vendor/src/config 완전 차단). mod_rewrite 가 없으면 동작하지 않으므로 precheck 는 mod_rewrite 의존성을 가정.
- **`PrecheckRunner` 는 외부 의존성을 전부 주입받는다**: `phpVersion`, `loadedExtensions`, `envPath`, `vendorAutoload`, `dbProbe` 를 생성자에서 받으므로 테스트 가능. 실제 `precheck.php` 웹 엔트리는 `PHP_VERSION` / `get_loaded_extensions()` / PDO 콜백을 주입한다. 시스템 호출을 직접 쓰는 구현으로 리팩터 금지.
- **번들용 vendor/ 분리**: `make dothome-bundle` 은 `docker compose run --rm --no-deps -v $(PWD)/deploy/build/public_html/app:/bundle -w /bundle php composer install --no-dev --optimize-autoloader` 로 **dev 의 `backend/vendor/` 를 건드리지 않고** 별도 디렉터리에 production-only 의존성을 설치한다. dev vendor 에 --no-dev 를 실행하면 PHPUnit/PHPStan 이 사라져 테스트가 깨진다.
- **ESLint `out/` ignore**: `BUILD_TARGET=dothome` 빌드는 `frontend/out/` 에 수천 개의 minified JS/HTML 을 생성. ESLint flat config `ignores` 에 `out/**` 가 없으면 `make lint-fe` 가 3000+ 에러로 실패한다.
- **PHPStan `array_values` on list 경고**: 이미 `[]; $a[] = ...` 로 쌓은 list 에 `array_values($checks)` 를 씌우면 `arrayValues.list` 경고. 이미 list 이면 변환 불필요.

### 11.11 체크리스트 — 새 기능 착수 시

1. PHPStan 회귀 후보?  →  `@return App<...>` 제네릭 / `(array) $row` / `list<X>` 재인덱스 / `match` default / 이미 list 면 `array_values` 빼기
2. Domain 로직인가?  →  Unit test 에 Clock/IdGen 주입 + 불변식 assert
3. DB 쿼리 추가했나?  →  IndexCoverageTest 에 EXPLAIN 한 줄 추가
4. 새 보호 페이지인가?  →  `useAuthHydrated()` 가드 패턴 사용 + static export 호환(`'use client'`) 확인
5. Presenter 에서 금액 내보내는가?  →  `toScale(2, HALF_UP)` 로 정규화
6. E2E 시나리오 있나?  →  watcher 는 click 전 Promise.all, 금액 텍스트는 `.first()`
7. 새 API 엔드포인트가 프론트와 대화하는가?  →  `lib/api/*.ts` 에 스키마 추가, `client.ts` 통해 호출 (상대경로 `/api`)
8. DB 스키마 변경?  →  Phinx 마이그레이션 추가. 닷홈 재배포는 install.php 재실행 불가이므로 별도 migrate 엔드포인트 설계 필요 (현재 Phase 7 범위 외).

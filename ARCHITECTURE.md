# ARCHITECTURE.md — Budget Book

## 레이어 구성

```
┌─────────────────────────────────────────────────────────────────┐
│  Interface/Http                                                 │
│  · Slim 4 routes, controllers, middleware (JWT / Admin / CORS)  │
│  · Validators, Presenters, JsonResponder                        │
│  · 얇은 어댑터 — 도메인/애플리케이션에 의존                      │
└───────────────▲────────────────────────────────────▲────────────┘
                │                                    │
┌───────────────┴──────────┐           ┌─────────────┴────────────┐
│  Application             │           │  Infrastructure          │
│  · Auth (Register/Login) │           │  · Database (Capsule)    │
│  · Admin (Approve/...)   │           │  · Eloquent Repositories │
│  · Ledger (Record/...)   │           │  · Security (JWT service)│
│  · DTO + Exceptions      │           │  · Implements Domain     │
│  · 순수 PHP, DB 의존 X    │           │    interfaces            │
└───────────────▲──────────┘           └─────────────▲────────────┘
                │                                    │
                └──────────────┬─────────────────────┘
                               │
                  ┌────────────┴────────────┐
                  │  Domain                 │
                  │  · Account (User/Email) │
                  │  · Ledger (Account,     │
                  │    JournalEntry, ...)   │
                  │  · Reporting (Balance-  │
                  │    Sheet/CashFlow/...)  │
                  │  · Clock                │
                  │  · 프레임워크 무의존    │
                  └─────────────────────────┘
```

**의존 방향**: Interface/Infrastructure → Application → Domain.
Domain은 어느 것에도 의존하지 않고 순수 PHP 객체만 둔다.

---

## 복식부기 핵심 불변식

### 1. `JournalEntry` 집계 루트 (`src/Domain/Ledger/JournalEntry.php`)

```php
$entry = JournalEntry::record(
    userId: 1,
    occurredOn: $date,
    memo: null,
    merchant: '이마트',
    paymentMethod: PaymentMethod::CARD,
    lines: [
        JournalEntryLine::debit($foodAccountId, BigDecimal::of('10000')),
        JournalEntryLine::credit($cardAccountId, BigDecimal::of('10000')),
    ],
);
```

- 라인 ≥ 2
- `Σ(debit) == Σ(credit)` (차대 균형)
- `Σ(debit) > 0` (0원 거래 거부)
- 각 라인은 debit/credit 중 **정확히 하나만 양수**

위반 시 `DomainException` — 저장 자체가 불가능.

DB 레벨에서도 보장:
```sql
CHECK (debit >= 0 AND credit >= 0)
CHECK ((debit = 0 AND credit > 0) OR (debit > 0 AND credit = 0))
```

### 2. 재무상태표 항등식 (`BalanceSheetService`)

```
자산 = 부채 + 자본 + 당기순이익 + 개시자본(초기 잔액)
```

`compute()` 마지막에 `LogicException` 으로 검증. 개시 잔액이 offsetting entry 없이 들어와도 서비스가 합성 equity 라인으로 자동 보정.

### 3. 현금흐름표 조정 (`CashFlowStatementService`)

```
Σ(영업 순 + 투자 순 + 재무 순) == 기말 현금성자산 − 기초 현금성자산
```

직접법: 현금성 ASSET(subtype ∈ CASH, BANK) 라인이 있는 분개만 대상, 상대편 라인의 `cash_flow_section` 으로 섹션 분류. 마지막에 `LogicException` 으로 검증.

---

## 사용자 입력 → 복식부기 자동 변환

`RecordJournalEntry` 유스케이스가 담당:

| 입력 | Dr | Cr |
| --- | --- | --- |
| EXPENSE + CASH | 카테고리 | 사용자 현금(subtype=CASH 자동 탐색) |
| EXPENSE + CARD | 카테고리 | 사용자 신용카드(subtype=CARD 자동 탐색) |
| EXPENSE + TRANSFER + counter | 카테고리 | counter ASSET/LIABILITY |
| INCOME + CASH | 현금 | 카테고리 |
| INCOME + TRANSFER + counter | counter ASSET | 카테고리 |
| INCOME + CARD | (거부) 422 |

모든 변환은 `JournalEntry::record()` 를 거치므로 차대 균형 불변식은 자동으로 강제.

---

## 가입·승인 라이프사이클

```
가입 (POST /api/auth/register)
    └─ status=PENDING

로그인 (POST /api/auth/login)
    ├─ PENDING → 403 account_pending
    ├─ SUSPENDED → 403 account_suspended
    └─ ACTIVE → 200 token pair

관리자 승인 (PATCH /api/admin/users/{id} { status: ACTIVE })
    └─ UpdateUser 유스케이스
        ├─ User.activate() → status=ACTIVE
        └─ SeedDefaultAccounts.seed(userId)
            └─ 27개 기본 계정 생성 (idempotent)
                ASSET: 현금, 주거래은행, 증권계좌
                LIABILITY: 신용카드, 대출
                EQUITY: 자본금, 이익잉여금
                INCOME: 급여, 상여금, ... (OPERATING/INVESTING/FINANCING)
                EXPENSE: 식비, 주거비, ... (OPERATING/FINANCING)
```

---

## 테스트 3층

| 계층 | 러너 | 대상 | 개수 |
| --- | --- | --- | --- |
| Unit | PHPUnit | Domain + Application (InMemoryRepo) | 약 90 |
| Integration | PHPUnit + MySQL test DB | Eloquent Repository, 마이그레이션, 인덱스 EXPLAIN | 약 25 |
| Feature | PHPUnit + Slim PSR-7 | HTTP 엔드포인트 (실 DB) | 약 45 |
| Unit/Component (FE) | Vitest + RTL | 폼/리스트 컴포넌트 | 28 |
| E2E | Playwright | 전체 사용자 플로우 | 4 |

**격리**: Integration/Feature 테스트는 `DatabaseTestCase` 가 각 테스트 전 트랜잭션 시작 → 테스트 후 롤백. E2E 는 dev DB 에 실제 쓰기.

---

## 데이터베이스 스키마 요약

| 테이블 | 핵심 컬럼 | 인덱스 |
| --- | --- | --- |
| `users` | email (uniq), status (enum), role (enum), deleted_at | `uniq(email)`, `(status)` |
| `accounts` | user_id, account_type (enum 5), subtype, cash_flow_section, normal_balance, opening_balance, is_system, deleted_at | `(user_id, account_type)`, `(user_id, deleted_at)`, FK users |
| `journal_entries` | user_id, occurred_on, payment_method, source, deleted_at | `(user_id, occurred_on)`, `(user_id, deleted_at)`, FK users |
| `journal_entry_lines` | entry_id, account_id, debit, credit, line_no | `(entry_id)`, `(account_id, entry_id)`, CHECKs, FK entries/accounts |

### 조회 핫패스 (인덱스 커버리지 — `IndexCoverageTest.php` 로 자동 검증)
- 이메일 로그인: `users(email)`
- 사용자별 계정 조회: `accounts(user_id, ...)`
- 사용자 + 기간 분개 조회: `journal_entries(user_id, occurred_on)`
- 분개별 라인 로드: `journal_entry_lines(entry_id)`
- 계정별 라인 집계 (보고서): `journal_entry_lines(account_id, entry_id)`

---

## 기술적 "절대 규칙" (CLAUDE.md 요약)

변경 시 CLAUDE.md §1 · §7 · §9 를 반드시 참조.

- 스택 변경 금지 (Slim/Next.js/Vitest/Playwright/MySQL 8 고정)
- 단일 라인/불균형 분개 저장 금지
- `transactions` 테이블 신규 생성 금지 (`journal_entries` 사용)
- 하드 삭제 금지 (soft delete only)
- 금액에 `float` 사용 금지 (`DECIMAL(18,2)` + `brick/math` BigDecimal)
- `currency` 컬럼 추가 금지 (KRW 고정)
- 프론트 `fetch` 원시 사용 금지 (`lib/api/client.ts` 래퍼 경유)

---

## 파일 탐색 지도

- **API 진입점**: `backend/public/index.php` → `src/Bootstrap/AppFactory.php`
- **DI 컨테이너**: `src/Bootstrap/Container.php`
- **데이터베이스 초기화**: `src/Infrastructure/Database/ConnectionFactory.php`
- **복식부기 도메인**: `src/Domain/Ledger/`
- **보고서 서비스**: `src/Domain/Reporting/`
- **관리자 유스케이스**: `src/Application/Admin/`
- **프론트 인증 상태**: `frontend/lib/stores/auth.ts` (`useAuthHydrated` 포함)
- **프론트 API 클라이언트**: `frontend/lib/api/`
- **프론트 페이지**: `frontend/app/(app)/*`, `frontend/app/(admin)/*`, `frontend/app/(auth)/*`

# 닷홈 무료호스팅 배포 가이드 — samdogs.dothome.co.kr

> Budget Book 을 닷홈(dothome.co.kr) 무료호스팅에 **same-origin** 구조로 배포한다.
> 프론트(Next.js static export) + 백엔드(Slim/PHP) 를 한 도메인 한 docroot 로 합쳐 FTP 업로드만으로 운영한다.

## 요구 사양 (닷홈 무료호스팅 기준)

| 항목 | 값 |
|---|---|
| PHP | **8.4** (관리패널 > 호스팅 관리에서 선택) |
| MySQL | 8.x (자동 제공) |
| 디스크 | 500MB (번들 약 25MB) |
| 접속 | **FTP 전용** (SSH/Composer/Cron 없음) |

## 배포 흐름

```
┌─────────────┐   make dothome-bundle    ┌───────────────┐   FTP    ┌──────────┐
│  로컬(Docker)│ ───────────────────────→ │  samdogs.zip  │ ────────→ │ 닷홈 docroot │
└─────────────┘                          └───────────────┘          └──────────┘
                                                                         │
                                                                         ▼
                                                      /api/precheck.php  →  /api/install.php
                                                      (환경 점검)         (관리자 생성)
                                                                         │
                                                                         ▼
                                             precheck.php / install.php  **삭제**
```

## 1단계 — 닷홈 준비

1. 닷홈 마이페이지에서 **samdogs** 무료호스팅을 연다.
2. "호스팅 관리 > PHP 버전" 에서 **PHP 8.4** 로 전환.
3. "호스팅 관리 > MySQL" 에서 DB 정보 확인 (DB 명/계정/비밀번호/호스트).
   - 일반적으로 DB 명과 MySQL 유저명은 `samdogs` 로 동일.
4. FTP 접속 정보(호스트/ID/비밀번호) 를 받아둔다.

## 2단계 — 로컬 번들 빌드

루트 디렉터리에서:

```bash
make dothome-bundle
```

결과물:

```
deploy/
├── build/public_html/           # 그대로 docroot 로 업로드할 트리
└── samdogs-bundle.zip           # 압축본 (약 4~5MB)
```

번들 구조는 다음과 같다:

```
public_html/
├── index.html, _next/, login/, ...   ← Next.js static export
├── api/
│   ├── index.php                     ← Slim 프론트 컨트롤러
│   ├── precheck.php                  ← 배포 전 환경 점검 (1회)
│   ├── install.php                   ← 초기 관리자 생성 (1회)
│   └── .htaccess                     ← .env/.lock 등 숨김파일 차단
├── app/                               ← 코드/의존성 (외부 접근 403)
│   ├── vendor/  src/  config/  database/
│   └── .htaccess                     ← Require all denied
├── .htaccess                         ← /api/* 라우팅, SPA fallback
└── .env.example                      ← 아래 3단계에서 .env 로 복사
```

## 3단계 — FTP 업로드

### 3-1. 전체 트리 업로드

FileZilla/WinSCP 등으로:

1. 닷홈 FTP 에 접속
2. docroot (보통 `/`) 로 들어감
3. `deploy/build/public_html/` **안의 모든 항목**을 업로드 (디렉터리 자체가 아니라 내용물)
   - `index.html`, `_next/`, `api/`, `app/`, `.htaccess`, `.env.example` 등

> ⚠️ `.htaccess` 와 `.env.example` 은 **hidden file** 이므로 FTP 클라이언트에서 "숨김 파일 보기" 를 켠다.

### 3-2. `.env` 작성

`.env.example` 을 `.env` 로 이름 변경하거나, 로컬에서 아래 내용을 채워 **public_html/.env** 로 업로드:

```env
DB_HOST=localhost
# DB_PORT 는 생략 (닷홈은 localhost 에 대해 unix socket 으로 붙음. 포트 지정 시 TCP 로 빠져 연결 실패).
# DB_PORT=3306
DB_DATABASE=samdogs
DB_USERNAME=samdogs
DB_PASSWORD=(닷홈 MySQL 비밀번호)

APP_ENV=production
APP_DEBUG=false
APP_TIMEZONE=Asia/Seoul
APP_URL=https://samdogs.dothome.co.kr

CORS_ALLOWED_ORIGIN=same-origin

JWT_SECRET=(openssl rand -base64 48 로 생성한 랜덤 문자열)
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=1209600
```

`JWT_SECRET` 은 반드시 **48자 이상 랜덤**으로 만든다. 로컬에서 :

```bash
openssl rand -base64 48
```

## 4단계 — 사전 점검 (precheck.php)

브라우저로 다음 주소를 연다:

```
https://samdogs.dothome.co.kr/api/precheck.php
```

결과:
- ✅ **ALL CHECKS PASSED** 가 보이면 다음 단계.
- ❌ 실패 항목이 보이면 메시지대로 수정 후 새로고침.

가장 자주 걸리는 항목:
- `php_version` — 관리패널에서 PHP 8.4 로 바꿨는지 확인
- `env_values` — `.env` 에 필수 키 누락 없는지
- `jwt_secret_strength` — `change-me` 같은 기본값은 거부됨 → 강한 시크릿 사용
- `database_connection` — `.env` 의 DB 정보 오류 / 닷홈 DB 호스트가 `localhost` 가 아닐 수 있음

## 5단계 — 초기 설치 (install.php)

```
https://samdogs.dothome.co.kr/api/install.php
```

폼 항목:
- 관리자 이메일
- 관리자 표시이름
- 관리자 비밀번호 (10자 이상)
- 비밀번호 확인

"설치 실행" 클릭 시:
1. 마이그레이션 3개 실행 (users / accounts / journal_entries)
2. 초기 admin 계정 생성 (ACTIVE + ADMIN + 시스템 계정과목 자동 시드)
3. `public_html/.installed` sentinel 파일 기록

재접근 시 **403 이미 설치됨** 안내가 뜨면 정상.

## 6단계 — 보안 정리 (매우 중요)

설치가 끝나면 FTP 로 다음 파일을 **반드시 삭제**:

```
public_html/api/precheck.php
public_html/api/install.php
```

`.installed` 파일도 남겨둘 필요가 없지만, 남겨두면 이후 실수 재설치를 막는다 (선택).

## 7단계 — 운영 확인

- `https://samdogs.dothome.co.kr/login` → 로그인 화면
- 설치 시 입력한 관리자 이메일/비밀번호로 로그인
- 대시보드 → 거래 입력 / 보고서 확인

## 문제 해결

| 증상 | 원인 | 해결 |
|---|---|---|
| 로그인 API 가 404 | `/api/*` 라우팅 미동작 | `public_html/.htaccess` 가 제대로 업로드되었는지 확인, mod_rewrite 미지원이면 닷홈 고객센터 문의 |
| `install.php` 가 500 | vendor/ 누락 또는 PHP 버전 | precheck.php 먼저 |
| DB 연결 실패 | `.env` DB 정보 또는 `DB_PORT` 지정 | 닷홈은 `DB_HOST=localhost` + `DB_PORT` 생략(소켓) 조합만 허용. 외부 호스트 케이스일 경우 관리패널의 실제 호스트명으로 교체 |
| SPA 새로고침 시 404 | .htaccess SPA fallback 미적용 | `public_html/.htaccess` 업로드 재확인 |
| 관리자 계정 분실 | install.php 재실행 불가 | 닷홈 phpMyAdmin 에서 `users` 테이블 수정 or DB 비우고 `.installed` 삭제 후 재설치 |

## 재배포 시

- **코드 변경만**: `make dothome-bundle` 재실행 → `public_html/` 내용 **덮어쓰기** 업로드. `.env` 와 `.installed` 는 보존.
- **DB 스키마 변경**: 새 Phinx 마이그레이션 추가 시 별도의 one-shot 엔드포인트 필요. (현재 Phase 7 범위 외 — `install.php` 는 최초 1회 한정.)

## 보안 체크리스트

- [ ] `.env` 파일의 `JWT_SECRET` 이 48자 이상 고유 랜덤이다
- [ ] `.env` 는 FTP 로만 올렸고 git 에는 커밋하지 않았다
- [ ] `api/install.php` 와 `api/precheck.php` 는 삭제했다
- [ ] `APP_DEBUG=false`, `APP_ENV=production` 이다
- [ ] `app/.htaccess` 가 존재한다 (vendor/.env 등 403)

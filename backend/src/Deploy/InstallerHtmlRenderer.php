<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

use Throwable;

final class InstallerHtmlRenderer
{
    /**
     * @param array<string, string> $errors  field → message
     * @param array<string, string> $values  field → value (repopulate form)
     */
    public function renderForm(array $errors = [], array $values = []): string
    {
        $emailErr = $this->errLine($errors['admin_email'] ?? null);
        $pwErr = $this->errLine($errors['admin_password'] ?? null);
        $pwConfirmErr = $this->errLine($errors['admin_password_confirm'] ?? null);
        $nameErr = $this->errLine($errors['admin_display_name'] ?? null);
        $topErr = $this->errLine($errors['_global'] ?? null);

        $emailVal = $this->h($values['admin_email'] ?? '');
        $nameVal = $this->h($values['admin_display_name'] ?? '관리자');

        return $this->layout('Budget Book — 설치', <<<HTML
<h1>Budget Book — 초기 설치</h1>
<p>이 페이지는 <strong>최초 1회</strong>만 실행합니다. 먼저 <code>precheck.php</code> 를 열어 모든 항목이 통과했는지 확인하세요.</p>
{$topErr}
<form method="post" novalidate>
  <label>관리자 이메일
    <input type="email" name="admin_email" value="{$emailVal}" required autocomplete="email">
  </label>
  {$emailErr}
  <label>관리자 표시이름
    <input type="text" name="admin_display_name" value="{$nameVal}" required maxlength="100">
  </label>
  {$nameErr}
  <label>관리자 비밀번호 (10자 이상)
    <input type="password" name="admin_password" required minlength="10" autocomplete="new-password">
  </label>
  {$pwErr}
  <label>비밀번호 확인
    <input type="password" name="admin_password_confirm" required minlength="10" autocomplete="new-password">
  </label>
  {$pwConfirmErr}
  <button type="submit">설치 실행</button>
</form>
<p class="note">설치가 끝난 뒤 FTP 클라이언트로 <code>install.php</code> 와 <code>precheck.php</code> 를 <strong>반드시 삭제</strong>하세요.</p>
HTML);
    }

    public function renderSuccess(InstallerResult $result): string
    {
        $email = $this->h($result->adminEmail);
        $count = $result->seededAccountCount;
        $migration = $this->h($result->migrationOutput);

        return $this->layout('Budget Book — 설치 완료', <<<HTML
<h1>✓ 설치 완료</h1>
<p>관리자 계정 <code>{$email}</code> 로 로그인하세요. 시스템 기본 계정과목 <strong>{$count}개</strong>가 자동 생성되었습니다.</p>
<h2>다음 단계 (중요)</h2>
<ol class="steps">
  <li>FTP 로 접속해 <code>public_html/install.php</code>, <code>public_html/precheck.php</code> 파일을 <strong>삭제</strong>하세요.</li>
  <li>사이트 루트에서 로그인 후 거래를 기록해보세요.</li>
</ol>
<details>
  <summary>마이그레이션 로그</summary>
  <pre>{$migration}</pre>
</details>
HTML);
    }

    public function renderAlreadyInstalled(string $sentinelPath): string
    {
        $path = $this->h($sentinelPath);
        return $this->layout('Budget Book — 이미 설치됨', <<<HTML
<h1>이미 설치되었습니다</h1>
<p>sentinel 파일 <code>{$path}</code> 이 존재합니다. 재설치하려면 DB 를 비우고 해당 파일을 삭제한 뒤 다시 시도하세요.</p>
<p>설치가 이미 끝났다면 <code>install.php</code> 와 <code>precheck.php</code> 파일도 함께 삭제하는 것이 안전합니다.</p>
HTML);
    }

    public function renderError(Throwable $e): string
    {
        $msg = $this->h($e->getMessage());
        $type = $this->h($e::class);
        return $this->layout('Budget Book — 설치 실패', <<<HTML
<h1>✗ 설치 실패</h1>
<p class="err">{$type}: {$msg}</p>
<p>원인을 해결한 뒤 <a href="./install.php">다시 시도</a>하세요.</p>
HTML);
    }

    private function errLine(?string $msg): string
    {
        if ($msg === null || $msg === '') {
            return '';
        }
        return '<div class="err">' . $this->h($msg) . '</div>';
    }

    private function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private function layout(string $title, string $body): string
    {
        $t = $this->h($title);
        return <<<HTML
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>{$t}</title>
<style>
body { font-family: -apple-system, "Segoe UI", "Malgun Gothic", sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; color: #111; line-height: 1.55; }
h1 { font-size: 1.4rem; }
label { display: block; margin: .8rem 0 .2rem; font-weight: 600; font-size: .95rem; }
input { display: block; width: 100%; padding: .55rem .6rem; border: 1px solid #ccd; border-radius: 5px; font-size: 1rem; box-sizing: border-box; }
button { margin-top: 1.2rem; padding: .6rem 1.2rem; background: #1b4bff; color: #fff; border: 0; border-radius: 5px; font-weight: 600; cursor: pointer; }
button:hover { background: #0d36ca; }
.err { color: #8a1a1a; background: #fdecec; padding: .5rem .7rem; border-radius: 4px; margin: .3rem 0; font-size: .9rem; }
.note { margin-top: 1.2rem; padding: .7rem 1rem; background: #fffbea; border-left: 4px solid #f3c33c; font-size: .9rem; }
.steps li { margin: .3rem 0; }
pre { background: #f1f3f5; padding: .7rem; border-radius: 4px; overflow-x: auto; font-size: .85rem; }
code { background: #eef; padding: 0 .3rem; border-radius: 3px; }
</style>
</head>
<body>
{$body}
</body>
</html>
HTML;
    }
}

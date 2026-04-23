<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final class PrecheckHtmlRenderer
{
    public function render(PrecheckResult $result): string
    {
        $ok = $result->isOk();
        $banner = $ok
            ? '<div class="ok">✓ ALL CHECKS PASSED — install.php 실행이 가능합니다.</div>'
            : '<div class="fail">✗ CHECKS FAILED — 아래 항목을 해결한 뒤 다시 precheck.php 를 여세요.</div>';

        $rows = '';
        foreach ($result->checks as $check) {
            $cls = $check->passed ? 'row pass' : 'row fail';
            $icon = $check->passed ? '✓' : '✗';
            $rows .= sprintf(
                '<div class="%s"><span class="icon">%s</span><span class="name">%s</span><span class="msg">%s</span></div>' . "\n",
                htmlspecialchars($cls, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($check->name, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($check->message, ENT_QUOTES, 'UTF-8'),
            );
        }

        $nextStep = $ok
            ? '<p class="next">다음 단계: <code>install.php</code> 를 실행해 DB 마이그레이션과 초기 admin 계정을 생성하세요. 설치가 끝나면 <strong>precheck.php 와 install.php 를 반드시 삭제</strong>해야 합니다.</p>'
            : '<p class="next">실패 항목을 해결한 뒤 새로고침하세요.</p>';

        return <<<HTML
<!doctype html>
<html lang="ko">
<head>
<meta charset="utf-8">
<title>Budget Book — 배포 사전 점검</title>
<style>
body { font-family: -apple-system, "Segoe UI", "Malgun Gothic", sans-serif; max-width: 820px; margin: 2rem auto; padding: 0 1rem; color: #111; }
h1 { font-size: 1.4rem; }
.ok { background: #e6f7ed; color: #0a6b2a; padding: .8rem 1rem; border-radius: 6px; font-weight: 600; }
.fail { background: #fdecec; color: #8a1a1a; padding: .8rem 1rem; border-radius: 6px; font-weight: 600; }
.row { display: grid; grid-template-columns: 2rem 10rem 1fr; gap: .5rem; padding: .4rem 0; border-bottom: 1px solid #eee; font-size: .92rem; }
.row.pass .icon { color: #0a6b2a; }
.row.fail .icon { color: #8a1a1a; }
.row .name { font-family: ui-monospace, "SF Mono", Menlo, monospace; }
.next { margin-top: 1.4rem; padding: 1rem; background: #f4f6fa; border-radius: 6px; line-height: 1.5; }
code { background: #eef; padding: 0 .3rem; border-radius: 3px; }
</style>
</head>
<body>
<h1>Budget Book — 배포 사전 점검 (precheck)</h1>
{$banner}
<div class="checks">
{$rows}</div>
{$nextStep}
</body>
</html>
HTML;
    }
}

<?php

declare(strict_types=1);

/**
 * 닷홈 배포용 사전 점검 스크립트.
 * 구조:
 *   public_html/
 *   ├── api/precheck.php  ← 이 파일
 *   ├── app/vendor, config, src, database
 *   └── .env
 *
 * 설치 완료 후 반드시 삭제.
 */

use BudgetBook\Deploy\PrecheckHtmlRenderer;
use BudgetBook\Deploy\PrecheckRunner;

$autoload = __DIR__ . '/../app/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><h1>Precheck 실패</h1>';
    echo '<p>app/vendor/ 가 업로드되지 않았습니다. 로컬에서 <code>make dothome-bundle</code> 로 번들을 다시 생성해 업로드하세요.</p>';
    exit;
}
require $autoload;

$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    \Dotenv\Dotenv::createImmutable(dirname($envPath), basename($envPath))->safeLoad();
}

$runner = new PrecheckRunner(
    phpVersion: PHP_VERSION,
    loadedExtensions: get_loaded_extensions(),
    envPath: $envPath,
    vendorAutoload: $autoload,
    dbProbe: static function (array $env): bool {
        // DB_PORT 가 비어 있으면 DSN 에서 port 조각을 생략. 닷홈 같은 shared-host 는
        // host=localhost + port 미지정 상태로만 unix socket 접속이 성립한다.
        $port = $env['DB_PORT'] ?? '';
        $dsn = 'mysql:host=' . ($env['DB_HOST'] ?? '')
            . ($port !== '' ? ';port=' . $port : '')
            . ';dbname=' . ($env['DB_DATABASE'] ?? '')
            . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, $env['DB_USERNAME'] ?? '', $env['DB_PASSWORD'] ?? '', [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
        return true;
    },
);

$result = $runner->run();

header('Content-Type: text/html; charset=utf-8');
if (!$result->isOk()) {
    http_response_code(503);
}
echo (new PrecheckHtmlRenderer())->render($result);

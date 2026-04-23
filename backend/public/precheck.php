<?php

declare(strict_types=1);

/**
 * 닷홈(혹은 임의 PHP 호스팅) 배포 시 먼저 열어 설치 가능 여부를 점검하는 진단 스크립트.
 * 모든 항목이 통과해야 install.php 를 실행해도 안전하다.
 *
 * 설치 완료 후 반드시 삭제할 것.
 */

use BudgetBook\Deploy\PrecheckHtmlRenderer;
use BudgetBook\Deploy\PrecheckRunner;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><h1>Precheck 실패</h1>';
    echo '<p><code>' . htmlspecialchars($autoload, ENT_QUOTES, 'UTF-8')
        . '</code> 를 찾을 수 없습니다. 로컬에서 <code>composer install --no-dev --optimize-autoloader</code> 후 vendor/ 디렉터리를 함께 업로드하세요.</p>';
    exit;
}
require $autoload;

$envPath = getenv('BB_ENV_PATH') ?: __DIR__ . '/../.env';
if (is_file($envPath)) {
    \Dotenv\Dotenv::createImmutable(dirname($envPath), basename($envPath))->safeLoad();
}

$runner = new PrecheckRunner(
    phpVersion: PHP_VERSION,
    loadedExtensions: get_loaded_extensions(),
    envPath: $envPath,
    vendorAutoload: $autoload,
    dbProbe: static function (array $env): bool {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $env['DB_HOST'] ?? '',
            $env['DB_PORT'] ?? '3306',
            $env['DB_DATABASE'] ?? '',
        );
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

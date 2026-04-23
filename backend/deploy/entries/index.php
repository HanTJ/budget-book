<?php

declare(strict_types=1);

/**
 * 닷홈 배포용 Slim 프론트 컨트롤러.
 * 배포 트리 구조:
 *   public_html/
 *   ├── api/index.php  ← 이 파일
 *   ├── app/vendor, src, config, database
 *   └── .env
 */

use BudgetBook\Bootstrap\AppFactory;

require __DIR__ . '/../app/vendor/autoload.php';

$envFile = __DIR__ . '/../.env';
if (is_file($envFile)) {
    Dotenv\Dotenv::createImmutable(dirname($envFile), basename($envFile))->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Seoul');

// 닷홈을 포함해 Apache + CGI/suPHP 환경에서는 'Authorization' 헤더가 PHP 까지 전달
// 되지 않는 경우가 있다. .htaccess 의 RewriteRule [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
// 로도 정상화되지 않는 호스트를 위해 진입 직후 여러 소스에서 복원한다.
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || $_SERVER['HTTP_AUTHORIZATION'] === '') {
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        if (is_array($requestHeaders)) {
            foreach ($requestHeaders as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0 && $value !== '') {
                    $_SERVER['HTTP_AUTHORIZATION'] = $value;
                    break;
                }
            }
        }
    }
}

AppFactory::create()->run();

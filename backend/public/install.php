<?php

declare(strict_types=1);

/**
 * 닷홈(혹은 임의 PHP 호스팅) 배포 1회 전용 설치 스크립트.
 * 반드시 precheck.php 를 먼저 실행해 모든 항목이 통과했는지 확인한 뒤 실행하세요.
 * 설치 완료 후 이 파일과 precheck.php 를 FTP 에서 삭제하십시오.
 */

use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Deploy\AlreadyInstalled;
use BudgetBook\Deploy\Installer;
use BudgetBook\Deploy\InstallerHtmlRenderer;
use BudgetBook\Deploy\InstallerInput;
use BudgetBook\Deploy\PhinxMigrator;
use BudgetBook\Domain\Clock\SystemClock;
use BudgetBook\Infrastructure\Database\ConnectionFactory;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentAccountRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><h1>설치 불가</h1><p>vendor/ 가 업로드되지 않았습니다. precheck.php 로 먼저 확인하세요.</p>';
    exit;
}
require $autoload;

$envPath = getenv('BB_ENV_PATH') ?: __DIR__ . '/../.env';
if (is_file($envPath)) {
    \Dotenv\Dotenv::createImmutable(dirname($envPath), basename($envPath))->safeLoad();
}

$sentinelPath = __DIR__ . '/../.installed';

$renderer = new InstallerHtmlRenderer();

$buildInstaller = static function () use ($sentinelPath): Installer {
    ConnectionFactory::boot();

    $accounts = new EloquentAccountRepository();

    return new Installer(
        users: new EloquentUserRepository(),
        accounts: $accounts,
        seed: new SeedDefaultAccounts($accounts),
        clock: new SystemClock(),
        migrator: new PhinxMigrator(
            configPath: __DIR__ . '/../config/phinx.php',
            environment: 'development',
        ),
        sentinelPath: $sentinelPath,
    );
};

header('Content-Type: text/html; charset=utf-8');

try {
    if (is_file($sentinelPath)) {
        http_response_code(403);
        echo $renderer->renderAlreadyInstalled($sentinelPath);
        exit;
    }

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') {
        echo $renderer->renderForm();
        exit;
    }

    $email = trim((string) ($_POST['admin_email'] ?? ''));
    $password = (string) ($_POST['admin_password'] ?? '');
    $confirm = (string) ($_POST['admin_password_confirm'] ?? '');
    $displayName = trim((string) ($_POST['admin_display_name'] ?? ''));

    $errors = [];
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['admin_email'] = '유효한 이메일을 입력하세요.';
    }
    if ($displayName === '') {
        $errors['admin_display_name'] = '표시 이름을 입력하세요.';
    }
    if (strlen($password) < 10) {
        $errors['admin_password'] = '비밀번호는 10자 이상이어야 합니다.';
    }
    if ($password !== $confirm) {
        $errors['admin_password_confirm'] = '비밀번호 확인이 일치하지 않습니다.';
    }

    if ($errors !== []) {
        http_response_code(422);
        echo $renderer->renderForm($errors, [
            'admin_email' => $email,
            'admin_display_name' => $displayName,
        ]);
        exit;
    }

    $installer = $buildInstaller();
    $result = $installer->run(new InstallerInput(
        adminEmail: $email,
        adminPassword: $password,
        adminDisplayName: $displayName,
    ));

    echo $renderer->renderSuccess($result);
} catch (AlreadyInstalled $e) {
    http_response_code(403);
    echo $renderer->renderAlreadyInstalled($sentinelPath);
} catch (\Throwable $e) {
    http_response_code(500);
    echo $renderer->renderError($e);
}

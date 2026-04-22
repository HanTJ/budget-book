<?php

declare(strict_types=1);

use BudgetBook\Bootstrap\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/../../')->safeLoad();
}

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Seoul');

AppFactory::create()->run();

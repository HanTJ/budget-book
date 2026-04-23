<?php

declare(strict_types=1);

// DB_PORT 가 비어 있으면 Phinx adapter DSN 에서 port 를 생략한다 (닷홈 소켓 접속 호환).
$port = $_ENV['DB_PORT'] ?? '';
$makeEnv = static function (string $database) use ($port): array {
    $env = [
        'adapter' => 'mysql',
        'host' => $_ENV['DB_HOST'] ?? 'mysql',
        'name' => $database,
        'user' => $_ENV['DB_USERNAME'] ?? 'budget',
        'pass' => $_ENV['DB_PASSWORD'] ?? 'budget',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_0900_ai_ci',
    ];
    if ($port !== '') {
        $env['port'] = (int) $port;
    }
    return $env;
};

return [
    'paths' => [
        'migrations' => __DIR__ . '/../database/migrations',
        'seeds' => __DIR__ . '/../database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => $makeEnv($_ENV['DB_DATABASE'] ?? 'budget_book'),
        'testing' => $makeEnv($_ENV['DB_TEST_DATABASE'] ?? 'budget_book_test'),
    ],
    'version_order' => 'creation',
];

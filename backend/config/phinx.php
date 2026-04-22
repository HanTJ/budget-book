<?php

declare(strict_types=1);

return [
    'paths' => [
        'migrations' => __DIR__ . '/../database/migrations',
        'seeds' => __DIR__ . '/../database/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'name' => $_ENV['DB_DATABASE'] ?? 'budget_book',
            'user' => $_ENV['DB_USERNAME'] ?? 'budget',
            'pass' => $_ENV['DB_PASSWORD'] ?? 'budget',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
        ],
        'testing' => [
            'adapter' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'name' => $_ENV['DB_TEST_DATABASE'] ?? 'budget_book_test',
            'user' => $_ENV['DB_USERNAME'] ?? 'budget',
            'pass' => $_ENV['DB_PASSWORD'] ?? 'budget',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
        ],
    ],
    'version_order' => 'creation',
];

<?php

declare(strict_types=1);

namespace BudgetBook\Infrastructure\Database;

use Illuminate\Database\Capsule\Manager as Capsule;

final class ConnectionFactory
{
    private static ?Capsule $capsule = null;

    /**
     * @param array<string, string|int|null> $overrides
     */
    public static function boot(array $overrides = []): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule();
        $capsule->addConnection(array_merge([
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database' => $_ENV['DB_DATABASE'] ?? 'budget_book',
            'username' => $_ENV['DB_USERNAME'] ?? 'budget',
            'password' => $_ENV['DB_PASSWORD'] ?? 'budget',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'prefix' => '',
        ], $overrides));

        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;

        return $capsule;
    }

    public static function reset(): void
    {
        self::$capsule = null;
    }
}

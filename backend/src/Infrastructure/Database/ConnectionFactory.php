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

        $config = [
            'driver' => 'mysql',
            'host' => $_ENV['DB_HOST'] ?? 'mysql',
            'database' => $_ENV['DB_DATABASE'] ?? 'budget_book',
            'username' => $_ENV['DB_USERNAME'] ?? 'budget',
            'password' => $_ENV['DB_PASSWORD'] ?? 'budget',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_0900_ai_ci',
            'prefix' => '',
        ];
        // DB_PORT 가 비어 있으면 DSN 에서 port 를 아예 생략. 닷홈처럼 localhost 소켓
        // 경로만 허용하는 shared-host 에서 TCP 로 빠지지 않도록 하기 위함.
        $port = $_ENV['DB_PORT'] ?? '';
        if ($port !== '') {
            $config['port'] = (int) $port;
        }

        $capsule = new Capsule();
        $capsule->addConnection(array_merge($config, $overrides));

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

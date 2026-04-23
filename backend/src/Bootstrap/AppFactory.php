<?php

declare(strict_types=1);

namespace BudgetBook\Bootstrap;

use BudgetBook\Infrastructure\Database\ConnectionFactory;
use BudgetBook\Interface\Http\Controllers\Admin\AdminUsersController;
use BudgetBook\Interface\Http\Controllers\Auth\LoginController;
use BudgetBook\Interface\Http\Controllers\Auth\RegisterController;
use BudgetBook\Interface\Http\Controllers\HealthController;
use BudgetBook\Interface\Http\Controllers\Ledger\AccountController;
use BudgetBook\Interface\Http\Controllers\Ledger\JournalEntryController;
use BudgetBook\Interface\Http\Controllers\MeController;
use BudgetBook\Interface\Http\Controllers\Reporting\ReportsController;
use BudgetBook\Interface\Http\Middleware\AdminAuthMiddleware;
use BudgetBook\Interface\Http\Middleware\CorsMiddleware;
use BudgetBook\Interface\Http\Middleware\JwtAuthMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    /**
     * @return App<ContainerInterface|null>
     */
    public static function create(): App
    {
        ConnectionFactory::boot();

        $container = Container::build();
        SlimAppFactory::setContainer($container);
        $app = SlimAppFactory::create();

        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();

        // CORS 는 프론트와 백엔드가 다른 origin 일 때만 필요.
        // 닷홈처럼 same-origin(한 도메인 내 /api 프리픽스) 배포인 경우
        // CORS_ALLOWED_ORIGIN 을 비워두거나 'same-origin' 으로 지정하면 미들웨어를 생략한다.
        $allowedOrigin = $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'http://localhost:3000';
        if ($allowedOrigin !== '' && $allowedOrigin !== 'same-origin') {
            $app->add(new CorsMiddleware($allowedOrigin));
        }

        $app->addErrorMiddleware(
            displayErrorDetails: ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            logErrors: true,
            logErrorDetails: true,
        );

        $app->get('/api/health', [HealthController::class, 'show']);
        $app->post('/api/auth/register', RegisterController::class);
        $app->post('/api/auth/login', LoginController::class);

        $app->get('/api/me', MeController::class)
            ->add(JwtAuthMiddleware::class);

        $app->group('/api/accounts', function ($group): void {
            $group->get('', [AccountController::class, 'list']);
            $group->post('', [AccountController::class, 'create']);
            $group->patch('/{id:[0-9]+}', [AccountController::class, 'patch']);
            $group->delete('/{id:[0-9]+}', [AccountController::class, 'destroy']);
        })->add(JwtAuthMiddleware::class);

        $app->group('/api/entries', function ($group): void {
            $group->get('', [JournalEntryController::class, 'list']);
            $group->post('', [JournalEntryController::class, 'create']);
            $group->patch('/{id:[0-9]+}', [JournalEntryController::class, 'patch']);
            $group->delete('/{id:[0-9]+}', [JournalEntryController::class, 'destroy']);
        })->add(JwtAuthMiddleware::class);

        $app->group('/api/reports', function ($group): void {
            $group->get('/balance-sheet', [ReportsController::class, 'balanceSheet']);
            $group->get('/cash-flow', [ReportsController::class, 'cashFlow']);
            $group->get('/daily', [ReportsController::class, 'daily']);
        })->add(JwtAuthMiddleware::class);

        $app->group('/api/admin', function ($group): void {
            $group->get('/users', [AdminUsersController::class, 'list']);
            $group->patch('/users/{id:[0-9]+}', [AdminUsersController::class, 'patch']);
            $group->delete('/users/{id:[0-9]+}', [AdminUsersController::class, 'destroy']);
        })
            ->add(AdminAuthMiddleware::class)
            ->add(JwtAuthMiddleware::class);

        return $app;
    }
}

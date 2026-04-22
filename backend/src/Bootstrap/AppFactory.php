<?php

declare(strict_types=1);

namespace BudgetBook\Bootstrap;

use BudgetBook\Infrastructure\Database\ConnectionFactory;
use BudgetBook\Interface\Http\Controllers\Auth\LoginController;
use BudgetBook\Interface\Http\Controllers\Auth\RegisterController;
use BudgetBook\Interface\Http\Controllers\HealthController;
use BudgetBook\Interface\Http\Controllers\Ledger\AccountController;
use BudgetBook\Interface\Http\Controllers\MeController;
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

        $allowedOrigin = $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'http://localhost:3000';
        $app->add(new CorsMiddleware($allowedOrigin));

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

        return $app;
    }
}

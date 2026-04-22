<?php

declare(strict_types=1);

namespace BudgetBook\Bootstrap;

use BudgetBook\Interface\Http\Controllers\HealthController;
use Slim\App;
use Slim\Factory\AppFactory as SlimAppFactory;

final class AppFactory
{
    /**
     * @return App<\Psr\Container\ContainerInterface|null>
     */
    public static function create(): App
    {
        $app = SlimAppFactory::create();
        $app->addRoutingMiddleware();
        $app->addBodyParsingMiddleware();
        $app->addErrorMiddleware(
            displayErrorDetails: ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            logErrors: true,
            logErrorDetails: true,
        );

        $app->get('/api/health', [HealthController::class, 'show']);

        return $app;
    }
}

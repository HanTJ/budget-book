<?php

declare(strict_types=1);

namespace BudgetBook\Bootstrap;

use BudgetBook\Application\Auth\TokenService;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Clock\Clock;
use BudgetBook\Domain\Clock\SystemClock;
use BudgetBook\Domain\Ledger\AccountRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentAccountRepository;
use BudgetBook\Infrastructure\Persistence\Eloquent\EloquentUserRepository;
use BudgetBook\Infrastructure\Security\JwtTokenService;
use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

final class Container
{
    public static function build(): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->useAutowiring(true);

        $builder->addDefinitions([
            Clock::class => \DI\autowire(SystemClock::class),
            UserRepository::class => \DI\autowire(EloquentUserRepository::class),
            AccountRepository::class => \DI\autowire(EloquentAccountRepository::class),
            TokenService::class => static function (ContainerInterface $c): TokenService {
                $secret = (string) ($_ENV['JWT_SECRET'] ?? '');
                $accessTtl = (int) ($_ENV['JWT_ACCESS_TTL'] ?? 900);
                $refreshTtl = (int) ($_ENV['JWT_REFRESH_TTL'] ?? 1209600);
                /** @var Clock $clock */
                $clock = $c->get(Clock::class);
                return new JwtTokenService($secret, $accessTtl, $refreshTtl, $clock);
            },
        ]);

        return $builder->build();
    }
}

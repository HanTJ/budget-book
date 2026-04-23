<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

use Phinx\Console\PhinxApplication;
use Phinx\Wrapper\TextWrapper;

final class PhinxMigrator implements Migrator
{
    public function __construct(
        private readonly string $configPath,
        private readonly string $environment = 'development',
    ) {
    }

    public function migrate(): MigrationOutcome
    {
        $app = new PhinxApplication();
        $wrapper = new TextWrapper($app, [
            'configuration' => $this->configPath,
            'environment' => $this->environment,
        ]);

        $output = $wrapper->getMigrate();
        $exitCode = $wrapper->getExitCode();

        return new MigrationOutcome(exitCode: $exitCode, output: $output);
    }
}

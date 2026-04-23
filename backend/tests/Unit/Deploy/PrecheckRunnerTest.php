<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Deploy;

use BudgetBook\Deploy\PrecheckCheck;
use BudgetBook\Deploy\PrecheckResult;
use BudgetBook\Deploy\PrecheckRunner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecheckRunner::class)]
#[CoversClass(PrecheckResult::class)]
#[CoversClass(PrecheckCheck::class)]
final class PrecheckRunnerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/bb-precheck-' . uniqid();
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->recursiveDelete($this->tmpDir);
        parent::tearDown();
    }

    public function test_all_green_when_every_requirement_satisfied(): void
    {
        $envPath = $this->writeEnv([
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'x',
            'DB_USERNAME' => 'u',
            'DB_PASSWORD' => 'p',
            'JWT_SECRET' => str_repeat('a', 32),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Seoul',
        ]);
        $vendor = $this->touchFile('vendor/autoload.php');

        $runner = new PrecheckRunner(
            phpVersion: '8.4.0',
            loadedExtensions: ['pdo_mysql', 'mbstring', 'openssl', 'json', 'bcmath', 'intl', 'fileinfo', 'dom'],
            envPath: $envPath,
            vendorAutoload: $vendor,
            dbProbe: static fn () => true,
        );

        $result = $runner->run();

        self::assertTrue($result->isOk(), 'precheck should pass with all requirements met');
        self::assertNotEmpty($result->checks);
    }

    public function test_fails_when_php_version_too_low(): void
    {
        $runner = $this->runnerWith(phpVersion: '8.1.0');

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('php_version', $this->firstFailure($result)->name);
    }

    public function test_fails_when_required_extension_missing(): void
    {
        $runner = $this->runnerWith(loadedExtensions: ['mbstring', 'openssl', 'json', 'bcmath', 'intl', 'fileinfo', 'dom']);

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('extensions', $this->firstFailure($result)->name);
        self::assertStringContainsString('pdo_mysql', $this->firstFailure($result)->message);
    }

    public function test_fails_when_vendor_autoload_missing(): void
    {
        $runner = $this->runnerWith(vendorAutoload: $this->tmpDir . '/nope.php');

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('vendor_autoload', $this->firstFailure($result)->name);
    }

    public function test_fails_when_env_file_missing(): void
    {
        $runner = $this->runnerWith(envPath: $this->tmpDir . '/missing.env');

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('env_file', $this->firstFailure($result)->name);
    }

    public function test_fails_when_required_env_key_empty(): void
    {
        $envPath = $this->writeEnv([
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'x',
            'DB_USERNAME' => 'u',
            'DB_PASSWORD' => 'p',
            'JWT_SECRET' => '', // empty -> must fail
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Seoul',
        ]);
        $runner = $this->runnerWith(envPath: $envPath);

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('env_values', $this->firstFailure($result)->name);
        self::assertStringContainsString('JWT_SECRET', $this->firstFailure($result)->message);
    }

    public function test_fails_when_jwt_secret_weak(): void
    {
        $envPath = $this->writeEnv([
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'x',
            'DB_USERNAME' => 'u',
            'DB_PASSWORD' => 'p',
            'JWT_SECRET' => 'tooshort',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Seoul',
        ]);
        $runner = $this->runnerWith(envPath: $envPath);

        $result = $runner->run();

        self::assertFalse($result->isOk());
        $failure = $this->firstFailure($result);
        self::assertSame('jwt_secret_strength', $failure->name);
    }

    public function test_passes_when_db_port_missing_and_probe_succeeds(): void
    {
        // 닷홈 무료호스팅은 localhost 소켓 접속이라 .env 에 DB_PORT 를 비워두거나
        // 아예 키 자체를 빼야 PDO 가 TCP 대신 소켓으로 붙는다. DB_PORT 는 optional.
        $envPath = $this->writeEnv([
            'DB_HOST' => 'localhost',
            'DB_DATABASE' => 'samdogs',
            'DB_USERNAME' => 'samdogs',
            'DB_PASSWORD' => 'p',
            'JWT_SECRET' => str_repeat('a', 32),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Seoul',
        ]);
        $runner = $this->runnerWith(envPath: $envPath);

        $result = $runner->run();

        self::assertTrue($result->isOk(), 'precheck should pass without DB_PORT');
    }

    public function test_db_connection_message_omits_port_when_empty(): void
    {
        $envPath = $this->writeEnv([
            'DB_HOST' => 'localhost',
            'DB_DATABASE' => 'samdogs',
            'DB_USERNAME' => 'samdogs',
            'DB_PASSWORD' => 'p',
            'JWT_SECRET' => str_repeat('a', 32),
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_TIMEZONE' => 'Asia/Seoul',
        ]);
        $runner = $this->runnerWith(envPath: $envPath);

        $result = $runner->run();

        $dbCheck = $this->checkNamed($result, 'database_connection');
        self::assertTrue($dbCheck->passed);
        self::assertStringContainsString('samdogs@localhost/samdogs', $dbCheck->message);
        self::assertStringNotContainsString(':?', $dbCheck->message);
        self::assertStringNotContainsString(':3306', $dbCheck->message);
    }

    public function test_fails_when_db_probe_throws(): void
    {
        $runner = $this->runnerWith(dbProbe: static function (): bool {
            throw new \RuntimeException('connection refused');
        });

        $result = $runner->run();

        self::assertFalse($result->isOk());
        self::assertSame('database_connection', $this->firstFailure($result)->name);
        self::assertStringContainsString('connection refused', $this->firstFailure($result)->message);
    }

    /**
     * @param list<string>|null $loadedExtensions
     */
    private function runnerWith(
        ?string $phpVersion = null,
        ?array $loadedExtensions = null,
        ?string $envPath = null,
        ?string $vendorAutoload = null,
        ?\Closure $dbProbe = null,
    ): PrecheckRunner {
        return new PrecheckRunner(
            phpVersion: $phpVersion ?? '8.4.0',
            loadedExtensions: $loadedExtensions ?? ['pdo_mysql', 'mbstring', 'openssl', 'json', 'bcmath', 'intl', 'fileinfo', 'dom'],
            envPath: $envPath ?? $this->writeEnv([
                'DB_HOST' => 'localhost',
                'DB_PORT' => '3306',
                'DB_DATABASE' => 'x',
                'DB_USERNAME' => 'u',
                'DB_PASSWORD' => 'p',
                'JWT_SECRET' => str_repeat('a', 32),
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_TIMEZONE' => 'Asia/Seoul',
            ]),
            vendorAutoload: $vendorAutoload ?? $this->touchFile('vendor/autoload.php'),
            dbProbe: $dbProbe ?? static fn () => true,
        );
    }

    /**
     * @param array<string, string> $values
     */
    private function writeEnv(array $values): string
    {
        $path = $this->tmpDir . '/.env';
        $lines = [];
        foreach ($values as $k => $v) {
            $lines[] = sprintf('%s=%s', $k, $v);
        }
        file_put_contents($path, implode("\n", $lines));
        return $path;
    }

    private function touchFile(string $relative): string
    {
        $path = $this->tmpDir . '/' . $relative;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, '<?php');
        return $path;
    }

    private function firstFailure(PrecheckResult $result): PrecheckCheck
    {
        foreach ($result->checks as $check) {
            if (!$check->passed) {
                return $check;
            }
        }
        self::fail('expected at least one failing check');
    }

    private function checkNamed(PrecheckResult $result, string $name): PrecheckCheck
    {
        foreach ($result->checks as $check) {
            if ($check->name === $name) {
                return $check;
            }
        }
        self::fail(sprintf('no check named %s', $name));
    }

    private function recursiveDelete(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path)) {
            unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $this->recursiveDelete($path . '/' . $entry);
        }
        rmdir($path);
    }
}

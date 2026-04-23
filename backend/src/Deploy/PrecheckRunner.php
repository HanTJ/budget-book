<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

final class PrecheckRunner
{
    private const MIN_PHP_VERSION = '8.2.0';
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql',
        'mbstring',
        'openssl',
        'json',
        'bcmath',
        'intl',
        'fileinfo',
        'dom',
    ];
    // DB_PORT 는 optional. 닷홈 같은 shared-host 환경은 host=localhost + port 미지정이
    // 소켓 접속 경로를 타므로 port 를 요구하면 오히려 연결에 실패한다.
    private const REQUIRED_ENV_KEYS = [
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
        'DB_PASSWORD',
        'JWT_SECRET',
        'APP_ENV',
        'APP_DEBUG',
        'APP_TIMEZONE',
    ];
    private const MIN_JWT_SECRET_LENGTH = 32;

    private readonly \Closure $dbProbe;

    /**
     * @param list<string> $loadedExtensions
     * @param callable(array<string,string>): bool $dbProbe receives env map and returns true if DB reachable
     */
    public function __construct(
        private readonly string $phpVersion,
        private readonly array $loadedExtensions,
        private readonly string $envPath,
        private readonly string $vendorAutoload,
        callable $dbProbe,
    ) {
        $this->dbProbe = \Closure::fromCallable($dbProbe);
    }

    public function run(): PrecheckResult
    {
        $checks = [];
        $checks[] = $this->checkPhpVersion();
        $checks[] = $this->checkExtensions();
        $checks[] = $this->checkVendorAutoload();

        $envCheck = $this->checkEnvFile();
        $checks[] = $envCheck;

        if ($envCheck->passed) {
            $env = $this->parseEnv($this->envPath);
            $checks[] = $this->checkEnvValues($env);
            $checks[] = $this->checkJwtSecretStrength($env);
            $checks[] = $this->checkDatabaseConnection($env);
        }

        return new PrecheckResult($checks);
    }

    private function checkPhpVersion(): PrecheckCheck
    {
        $ok = version_compare($this->phpVersion, self::MIN_PHP_VERSION, '>=');
        return new PrecheckCheck(
            name: 'php_version',
            passed: $ok,
            message: $ok
                ? sprintf('PHP %s (>= %s required)', $this->phpVersion, self::MIN_PHP_VERSION)
                : sprintf('PHP %s is too old. Need >= %s. 닷홈 관리패널에서 PHP 8.4 를 선택하세요.', $this->phpVersion, self::MIN_PHP_VERSION),
        );
    }

    private function checkExtensions(): PrecheckCheck
    {
        $missing = array_values(array_diff(self::REQUIRED_EXTENSIONS, $this->loadedExtensions));
        $ok = $missing === [];
        return new PrecheckCheck(
            name: 'extensions',
            passed: $ok,
            message: $ok
                ? 'All required PHP extensions loaded.'
                : 'Missing extension(s): ' . implode(', ', $missing),
        );
    }

    private function checkVendorAutoload(): PrecheckCheck
    {
        $ok = is_file($this->vendorAutoload);
        return new PrecheckCheck(
            name: 'vendor_autoload',
            passed: $ok,
            message: $ok
                ? 'composer vendor/ present.'
                : 'vendor/autoload.php not found at ' . $this->vendorAutoload . '. 로컬에서 composer install --no-dev 후 vendor/ 디렉터리를 업로드하세요.',
        );
    }

    private function checkEnvFile(): PrecheckCheck
    {
        $ok = is_file($this->envPath) && is_readable($this->envPath);
        return new PrecheckCheck(
            name: 'env_file',
            passed: $ok,
            message: $ok
                ? '.env file readable at ' . $this->envPath
                : '.env not found or not readable at ' . $this->envPath,
        );
    }

    /**
     * @param array<string, string> $env
     */
    private function checkEnvValues(array $env): PrecheckCheck
    {
        $missing = [];
        foreach (self::REQUIRED_ENV_KEYS as $key) {
            if (!isset($env[$key]) || $env[$key] === '') {
                $missing[] = $key;
            }
        }
        $ok = $missing === [];
        return new PrecheckCheck(
            name: 'env_values',
            passed: $ok,
            message: $ok
                ? 'All required .env keys have values.'
                : 'Empty or missing .env key(s): ' . implode(', ', $missing),
        );
    }

    /**
     * @param array<string, string> $env
     */
    private function checkJwtSecretStrength(array $env): PrecheckCheck
    {
        $secret = $env['JWT_SECRET'] ?? '';
        $weakMarkers = ['change-me', 'secret', 'password'];
        $isWeak = strlen($secret) < self::MIN_JWT_SECRET_LENGTH;
        if (!$isWeak) {
            foreach ($weakMarkers as $marker) {
                if (stripos($secret, $marker) !== false) {
                    $isWeak = true;
                    break;
                }
            }
        }
        return new PrecheckCheck(
            name: 'jwt_secret_strength',
            passed: !$isWeak,
            message: $isWeak
                ? sprintf('JWT_SECRET is weak (need >= %d chars, avoid "change-me"/"secret"). openssl rand -base64 48 권장.', self::MIN_JWT_SECRET_LENGTH)
                : 'JWT_SECRET meets minimum strength.',
        );
    }

    /**
     * @param array<string, string> $env
     */
    private function checkDatabaseConnection(array $env): PrecheckCheck
    {
        try {
            $reachable = ($this->dbProbe)($env);
            $port = $env['DB_PORT'] ?? '';
            $hostPart = ($env['DB_HOST'] ?? '?') . ($port !== '' ? ':' . $port : '');
            return new PrecheckCheck(
                name: 'database_connection',
                passed: (bool) $reachable,
                message: $reachable
                    ? sprintf('Connected to %s@%s/%s', $env['DB_USERNAME'] ?? '?', $hostPart, $env['DB_DATABASE'] ?? '?')
                    : 'Database probe returned false.',
            );
        } catch (\Throwable $e) {
            return new PrecheckCheck(
                name: 'database_connection',
                passed: false,
                message: 'DB connection failed: ' . $e->getMessage(),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function parseEnv(string $path): array
    {
        $out = [];
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $out;
        }
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '#')) {
                continue;
            }
            $pos = strpos($trim, '=');
            if ($pos === false) {
                continue;
            }
            $k = trim(substr($trim, 0, $pos));
            $v = trim(substr($trim, $pos + 1));
            // strip optional surrounding quotes
            if (
                strlen($v) >= 2
                && (($v[0] === '"' && $v[strlen($v) - 1] === '"')
                    || ($v[0] === "'" && $v[strlen($v) - 1] === "'"))
            ) {
                $v = substr($v, 1, -1);
            }
            $out[$k] = $v;
        }
        return $out;
    }
}

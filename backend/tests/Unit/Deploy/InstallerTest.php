<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Deploy;

use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Deploy\Installer;
use BudgetBook\Deploy\InstallerInput;
use BudgetBook\Deploy\MigrationOutcome;
use BudgetBook\Deploy\Migrator;
use BudgetBook\Domain\Account\UserRole;
use BudgetBook\Domain\Account\UserStatus;
use BudgetBook\Tests\Support\InMemoryAccountRepository;
use BudgetBook\Tests\Support\InMemoryUserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Installer::class)]
#[CoversClass(InstallerInput::class)]
#[CoversClass(MigrationOutcome::class)]
final class InstallerTest extends TestCase
{
    private string $tmpDir;
    private InMemoryUserRepository $users;
    private InMemoryAccountRepository $accounts;
    private FixedClockForInstallerTest $clock;
    private FakeMigrator $migrator;
    private Installer $installer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/bb-installer-' . uniqid();
        mkdir($this->tmpDir, 0o777, true);

        $this->users = new InMemoryUserRepository();
        $this->accounts = new InMemoryAccountRepository();
        $this->clock = new FixedClockForInstallerTest(new DateTimeImmutable('2026-04-22T09:00:00+09:00'));
        $this->migrator = new FakeMigrator();

        $this->installer = new Installer(
            users: $this->users,
            accounts: $this->accounts,
            seed: new SeedDefaultAccounts($this->accounts),
            clock: $this->clock,
            migrator: $this->migrator,
            sentinelPath: $this->tmpDir . '/.installed',
        );
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (scandir($this->tmpDir) ?: [] as $e) {
                if ($e === '.' || $e === '..') {
                    continue;
                }
                unlink($this->tmpDir . '/' . $e);
            }
            rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    public function test_run_creates_active_admin_and_seeds_accounts(): void
    {
        $result = $this->installer->run(new InstallerInput(
            adminEmail: 'admin@samdogs.dothome.co.kr',
            adminPassword: 'correct-horse-battery-staple',
            adminDisplayName: '관리자',
        ));

        self::assertGreaterThan(0, $result->userId);
        self::assertTrue($this->migrator->called);

        $user = $this->users->findById($result->userId);
        self::assertNotNull($user);
        self::assertSame(UserRole::ADMIN, $user->role);
        self::assertSame(UserStatus::ACTIVE, $user->status);

        $seededAccounts = $this->accounts->listForUser($result->userId);
        self::assertNotEmpty($seededAccounts, 'admin must receive seeded chart of accounts');
        self::assertSame(count($seededAccounts), $result->seededAccountCount);
    }

    public function test_run_writes_sentinel_file(): void
    {
        $sentinel = $this->tmpDir . '/.installed';

        $this->installer->run(new InstallerInput(
            adminEmail: 'admin@example.com',
            adminPassword: 'correct-horse-battery-staple',
            adminDisplayName: 'Admin',
        ));

        self::assertFileExists($sentinel);
        $decoded = json_decode((string) file_get_contents($sentinel), true);
        self::assertIsArray($decoded);
        self::assertSame('admin@example.com', $decoded['admin_email']);
        self::assertArrayHasKey('installed_at', $decoded);
    }

    public function test_run_twice_raises_already_installed(): void
    {
        $this->installer->run(new InstallerInput(
            adminEmail: 'admin@example.com',
            adminPassword: 'correct-horse-battery-staple',
            adminDisplayName: 'Admin',
        ));

        $this->expectException(\BudgetBook\Deploy\AlreadyInstalled::class);
        $this->installer->run(new InstallerInput(
            adminEmail: 'other@example.com',
            adminPassword: 'correct-horse-battery-staple',
            adminDisplayName: 'X',
        ));
    }

    public function test_migration_failure_aborts_install(): void
    {
        $this->migrator->exitCode = 1;
        $this->migrator->output = 'ERROR: could not connect';

        try {
            $this->installer->run(new InstallerInput(
                adminEmail: 'admin@example.com',
                adminPassword: 'correct-horse-battery-staple',
                adminDisplayName: 'X',
            ));
            self::fail('expected MigrationFailed');
        } catch (\BudgetBook\Deploy\MigrationFailed $e) {
            self::assertStringContainsString('could not connect', $e->getMessage());
        }

        self::assertEmpty($this->users->listAll(null), 'no user should be created when migration fails');
        self::assertFileDoesNotExist($this->tmpDir . '/.installed');
    }

    public function test_weak_admin_password_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->installer->run(new InstallerInput(
            adminEmail: 'admin@example.com',
            adminPassword: 'short',
            adminDisplayName: 'X',
        ));
    }

    public function test_sentinel_write_failure_raises(): void
    {
        // sentinelPath 위치에 디렉터리가 있으면 file_put_contents 가 반드시 실패한다.
        $sentinelAsDir = $this->tmpDir . '/.installed';
        mkdir($sentinelAsDir);

        $installer = new Installer(
            users: $this->users,
            accounts: $this->accounts,
            seed: new SeedDefaultAccounts($this->accounts),
            clock: $this->clock,
            migrator: $this->migrator,
            sentinelPath: $sentinelAsDir,
        );

        $this->expectException(\RuntimeException::class);
        try {
            $installer->run(new InstallerInput(
                adminEmail: 'admin@example.com',
                adminPassword: 'correct-horse-battery-staple',
                adminDisplayName: 'X',
            ));
        } finally {
            rmdir($sentinelAsDir);
        }
    }

    public function test_sentinel_unwritable_aborts_before_user_creation(): void
    {
        // sentinel 쓰기가 불가능한 상태에서는 user / account 를 하나도 만들면 안 된다.
        // 쓰기 가능 여부를 install 시작 시점에 선제 검사해 abort.
        $sentinelAsDir = $this->tmpDir . '/.installed';
        mkdir($sentinelAsDir);

        $installer = new Installer(
            users: $this->users,
            accounts: $this->accounts,
            seed: new SeedDefaultAccounts($this->accounts),
            clock: $this->clock,
            migrator: $this->migrator,
            sentinelPath: $sentinelAsDir,
        );

        try {
            $installer->run(new InstallerInput(
                adminEmail: 'admin@example.com',
                adminPassword: 'correct-horse-battery-staple',
                adminDisplayName: 'X',
            ));
            self::fail('expected RuntimeException');
        } catch (\RuntimeException) {
            // expected
        } finally {
            rmdir($sentinelAsDir);
        }

        self::assertEmpty($this->users->listAll(null), 'users must not be created when sentinel write is impossible');
        self::assertFalse($this->migrator->called, 'migration must not run when sentinel is unwritable');
    }

    public function test_is_installed_reflects_sentinel_state(): void
    {
        self::assertFalse($this->installer->isInstalled());

        $this->installer->run(new InstallerInput(
            adminEmail: 'admin@example.com',
            adminPassword: 'correct-horse-battery-staple',
            adminDisplayName: 'X',
        ));

        self::assertTrue($this->installer->isInstalled());
    }
}

final class FixedClockForInstallerTest implements \BudgetBook\Domain\Clock\Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}

final class FakeMigrator implements Migrator
{
    public bool $called = false;
    public int $exitCode = 0;
    public string $output = 'migrated ok';

    public function migrate(): MigrationOutcome
    {
        $this->called = true;
        return new MigrationOutcome($this->exitCode, $this->output);
    }
}

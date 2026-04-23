<?php

declare(strict_types=1);

namespace BudgetBook\Deploy;

use BudgetBook\Application\Ledger\SeedDefaultAccounts;
use BudgetBook\Domain\Account\Email;
use BudgetBook\Domain\Account\HashedPassword;
use BudgetBook\Domain\Account\User;
use BudgetBook\Domain\Account\UserRepository;
use BudgetBook\Domain\Clock\Clock;
use BudgetBook\Domain\Ledger\AccountRepository;
use DomainException;
use InvalidArgumentException;

final class Installer
{
    private const MIN_PASSWORD_LENGTH = 10;

    public function __construct(
        private readonly UserRepository $users,
        private readonly AccountRepository $accounts,
        private readonly SeedDefaultAccounts $seed,
        private readonly Clock $clock,
        private readonly Migrator $migrator,
        private readonly string $sentinelPath,
    ) {
    }

    public function isInstalled(): bool
    {
        return is_file($this->sentinelPath);
    }

    public function run(InstallerInput $input): InstallerResult
    {
        if ($this->isInstalled()) {
            throw AlreadyInstalled::at($this->sentinelPath);
        }

        if (strlen($input->adminPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(sprintf(
                'Initial admin password must be at least %d characters.',
                self::MIN_PASSWORD_LENGTH,
            ));
        }
        if (trim($input->adminDisplayName) === '') {
            throw new InvalidArgumentException('Admin display name must not be empty.');
        }

        // 선제 쓰기 가능 확인. sentinel 을 쓸 수 없는 환경이라면 마이그레이션/사용자 생성
        // 전에 중단해야 DB 만 변경되고 sentinel 이 없어 재실행 시 계정이 중복 생성되는
        // 반쪽 설치 상태가 안 된다.
        $this->assertSentinelWritable();

        // Run DB migrations first (if they fail, no user is created)
        $outcome = $this->migrator->migrate();
        if (!$outcome->isSuccess()) {
            throw MigrationFailed::with($outcome);
        }

        $email = Email::of($input->adminEmail);
        if ($this->users->emailExists($email)) {
            throw new DomainException('Admin email already exists. Delete the user or wipe the DB to re-run install.');
        }

        $admin = User::register(
            email: $email,
            password: HashedPassword::fromPlainText($input->adminPassword),
            displayName: trim($input->adminDisplayName),
            clock: $this->clock,
        );
        $admin->activate();
        $admin->promoteToAdmin();
        $this->users->save($admin);

        $adminId = $admin->id();
        if ($adminId === null) {
            throw new DomainException('User id was not assigned after save.');
        }

        $this->seed->seed($adminId);

        $this->writeSentinel($input->adminEmail);

        return new InstallerResult(
            userId: $adminId,
            adminEmail: $email->value,
            seededAccountCount: $this->countSeeded($adminId),
            migrationOutput: $outcome->output,
        );
    }

    private function assertSentinelWritable(): void
    {
        $dir = dirname($this->sentinelPath);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf(
                'Cannot create sentinel directory %s (권한 문제). 설치 전에 docroot 쓰기 권한을 확인하세요.',
                $dir,
            ));
        }

        $probe = $dir . '/.bb-install-probe-' . bin2hex(random_bytes(4));
        $written = @file_put_contents($probe, '');
        if ($written === false) {
            throw new \RuntimeException(sprintf(
                '설치 sentinel 을 쓸 수 없습니다 (%s). 먼저 docroot 쓰기 권한을 확인하세요. 이 상태로 설치를 진행하면 반쪽 상태가 생깁니다.',
                $dir,
            ));
        }
        @unlink($probe);

        // sentinel 자체가 디렉터리면 이후 file_put_contents 실패하므로 바로 감지.
        if (is_dir($this->sentinelPath)) {
            throw new \RuntimeException(sprintf(
                'Sentinel path %s is a directory. Remove it and retry.',
                $this->sentinelPath,
            ));
        }
    }

    private function writeSentinel(string $adminEmail): void
    {
        $payload = json_encode([
            'installed_at' => $this->clock->now()->format(\DATE_ATOM),
            'admin_email' => $adminEmail,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $dir = dirname($this->sentinelPath);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Cannot create sentinel directory %s', $dir));
        }

        // 쓰기 실패를 잡지 못하면 설치가 "완료"된 것처럼 보이지만 sentinel 이 없어
        // 재실행이 허용돼 관리자 계정이 중복 생성된다. 실패 시 반드시 예외.
        $written = @file_put_contents($this->sentinelPath, $payload);
        if ($written === false) {
            throw new \RuntimeException(sprintf(
                'Failed to write install sentinel %s. Check filesystem permissions (FTP 업로드 권한 또는 docroot 쓰기 권한).',
                $this->sentinelPath,
            ));
        }
    }

    private function countSeeded(int $userId): int
    {
        return count($this->accounts->listForUser($userId));
    }
}

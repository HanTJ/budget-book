<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

final class InitialAdminSeeder extends AbstractSeed
{
    public function run(): void
    {
        $email = strtolower(trim((string) ($_ENV['INITIAL_ADMIN_EMAIL'] ?? '')));
        $password = (string) ($_ENV['INITIAL_ADMIN_PASSWORD'] ?? '');
        $name = (string) ($_ENV['INITIAL_ADMIN_NAME'] ?? 'Administrator');

        if ($email === '' || strlen($password) < 8) {
            $this->output?->writeln('Skipping admin seed: INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD (>=8) are required.');
            return;
        }

        $pdo = $this->getAdapter()->getConnection();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($existing === false) {
            $insert = $pdo->prepare(
                'INSERT INTO users (email, password_hash, display_name, role, status, created_at, updated_at)
                 VALUES (?, ?, ?, "ADMIN", "ACTIVE", ?, ?)'
            );
            $insert->execute([$email, $hash, $name, $now, $now]);
            $this->output?->writeln(sprintf('Seeded ADMIN user %s.', $email));
            return;
        }

        $update = $pdo->prepare(
            "UPDATE users
             SET role = 'ADMIN', status = 'ACTIVE', password_hash = ?,
                 display_name = ?, deleted_at = NULL, updated_at = ?
             WHERE email = ?"
        );
        $update->execute([$hash, $name, $now, $email]);
        $this->output?->writeln(sprintf('Promoted existing user %s to ADMIN/ACTIVE.', $email));
    }
}

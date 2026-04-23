<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Deploy;

use BudgetBook\Deploy\InstallerHtmlRenderer;
use BudgetBook\Deploy\InstallerResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(InstallerHtmlRenderer::class)]
final class InstallerHtmlRendererTest extends TestCase
{
    public function test_form_contains_required_inputs(): void
    {
        $html = (new InstallerHtmlRenderer())->renderForm();

        self::assertStringContainsString('name="admin_email"', $html);
        self::assertStringContainsString('name="admin_password"', $html);
        self::assertStringContainsString('name="admin_password_confirm"', $html);
        self::assertStringContainsString('name="admin_display_name"', $html);
        self::assertStringContainsString('method="post"', $html);
    }

    public function test_form_renders_validation_errors(): void
    {
        $html = (new InstallerHtmlRenderer())->renderForm(errors: [
            'admin_password' => '비밀번호는 10자 이상이어야 합니다',
        ]);

        self::assertStringContainsString('비밀번호는 10자 이상', $html);
    }

    public function test_form_preserves_prior_values_on_error(): void
    {
        $html = (new InstallerHtmlRenderer())->renderForm(
            errors: ['admin_password' => '...'],
            values: ['admin_email' => 'keep@me.test'],
        );

        self::assertStringContainsString('value="keep@me.test"', $html);
    }

    public function test_success_page_confirms_admin_created(): void
    {
        $result = new InstallerResult(
            userId: 1,
            adminEmail: 'admin@example.com',
            seededAccountCount: 12,
            migrationOutput: 'migrated 3 files',
        );

        $html = (new InstallerHtmlRenderer())->renderSuccess($result);

        self::assertStringContainsString('설치 완료', $html);
        self::assertStringContainsString('admin@example.com', $html);
        self::assertStringContainsString('12', $html); // seeded account count
        self::assertStringContainsString('install.php', $html); // mention delete
        self::assertStringContainsString('precheck.php', $html);
    }

    public function test_already_installed_shown(): void
    {
        $html = (new InstallerHtmlRenderer())->renderAlreadyInstalled('/var/www/.installed');

        self::assertStringContainsString('이미 설치', $html);
        self::assertStringContainsString('/var/www/.installed', $html);
    }

    public function test_error_page_escapes_message(): void
    {
        $html = (new InstallerHtmlRenderer())->renderError(new \RuntimeException('<b>boom</b>'));

        self::assertStringNotContainsString('<b>boom</b>', $html);
        self::assertStringContainsString('&lt;b&gt;boom&lt;/b&gt;', $html);
    }

    public function test_form_escapes_old_values(): void
    {
        $html = (new InstallerHtmlRenderer())->renderForm(
            values: ['admin_email' => '"><script>alert(1)</script>'],
        );

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
    }
}

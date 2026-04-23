<?php

declare(strict_types=1);

namespace BudgetBook\Tests\Unit\Deploy;

use BudgetBook\Deploy\PrecheckCheck;
use BudgetBook\Deploy\PrecheckHtmlRenderer;
use BudgetBook\Deploy\PrecheckResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PrecheckHtmlRenderer::class)]
final class PrecheckHtmlRendererTest extends TestCase
{
    public function test_renders_ok_banner_when_all_pass(): void
    {
        $result = new PrecheckResult([
            new PrecheckCheck('php_version', true, 'PHP 8.4.0'),
            new PrecheckCheck('extensions', true, 'ok'),
        ]);

        $html = (new PrecheckHtmlRenderer())->render($result);

        self::assertStringContainsString('<!doctype html>', strtolower($html));
        self::assertStringContainsString('ALL CHECKS PASSED', $html);
        self::assertStringContainsString('install.php', $html);
    }

    public function test_renders_fail_banner_and_lists_failures(): void
    {
        $result = new PrecheckResult([
            new PrecheckCheck('php_version', true, 'PHP 8.4.0'),
            new PrecheckCheck('env_file', false, '.env missing'),
        ]);

        $html = (new PrecheckHtmlRenderer())->render($result);

        self::assertStringContainsString('CHECKS FAILED', $html);
        self::assertStringContainsString('.env missing', $html);
        self::assertStringContainsString('env_file', $html);
    }

    public function test_escapes_html_entities_in_messages(): void
    {
        $result = new PrecheckResult([
            new PrecheckCheck('x', false, '<script>alert(1)</script>'),
        ]);

        $html = (new PrecheckHtmlRenderer())->render($result);

        self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }
}

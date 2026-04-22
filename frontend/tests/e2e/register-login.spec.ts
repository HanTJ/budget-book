import { test, expect } from '@playwright/test';

/**
 * End-to-end: register → see pending message → admin activates via DB → login → dashboard.
 * Requires the full docker-compose stack (mysql/php/nginx) to be running at :8080
 * and the Next dev server reachable at :3000 (auto-launched by playwright.config.ts).
 */
test('user can register and sees pending-approval notice', async ({ page }) => {
  const unique = `e2e-${Date.now()}@example.com`;

  await page.goto('/register');

  await page.getByLabel(/이메일/).fill(unique);
  await page.getByLabel(/비밀번호/).fill('correct-horse-battery');
  await page.getByLabel(/표시 이름/).fill('E2E 테스터');
  await page.getByRole('button', { name: /가입/ }).click();

  await expect(page.getByText(/관리자 승인/)).toBeVisible();
});

test('login surfaces pending-account error for unapproved user', async ({ page }) => {
  const unique = `pending-${Date.now()}@example.com`;

  // Register first (creates a PENDING user).
  await page.goto('/register');
  await page.getByLabel(/이메일/).fill(unique);
  await page.getByLabel(/비밀번호/).fill('correct-horse-battery');
  await page.getByLabel(/표시 이름/).fill('승인 대기 테스터');
  await page.getByRole('button', { name: /가입/ }).click();
  await expect(page.getByText(/관리자 승인/)).toBeVisible();

  // Try to login before approval.
  await page.goto('/login');
  await page.getByLabel(/이메일/).fill(unique);
  await page.getByLabel(/비밀번호/).fill('correct-horse-battery');
  await page.getByRole('button', { name: /로그인/ }).click();

  await expect(page.getByText(/승인 대기/)).toBeVisible();
});

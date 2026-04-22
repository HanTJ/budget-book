import { test, expect, request as apiRequest } from '@playwright/test';

const API = process.env['E2E_API_BASE'] ?? 'http://localhost:8080/api';
const ADMIN_EMAIL = process.env['INITIAL_ADMIN_EMAIL'] ?? 'admin@example.com';
const ADMIN_PASSWORD = process.env['INITIAL_ADMIN_PASSWORD'] ?? 'change-me-please';

/**
 * End-to-end: register -> admin approves -> login -> record cash expense ->
 * verify the entry appears on /transactions -> open /reports/balance-sheet
 * and /reports/cash-flow and assert the expected amounts + identity badges.
 */
test('full user journey: register -> approve -> record -> reports', async ({ page }) => {
  const stamp = Date.now();
  const email = `full-${stamp}@example.com`;
  const ctx = await apiRequest.newContext();

  const register = await ctx.post(`${API}/auth/register`, {
    data: { email, password: 'correct-horse-battery', display_name: `전체플로우 ${stamp}` },
  });
  expect(register.status()).toBe(201);
  const { id: userId } = (await register.json()) as { id: number };

  const adminLogin = await ctx.post(`${API}/auth/login`, {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
  });
  const { access_token: adminToken } = (await adminLogin.json()) as { access_token: string };

  const approve = await ctx.patch(`${API}/admin/users/${userId}`, {
    data: { status: 'ACTIVE' },
    headers: { Authorization: `Bearer ${adminToken}` },
  });
  expect(approve.status()).toBe(200);

  const userLogin = await ctx.post(`${API}/auth/login`, {
    data: { email, password: 'correct-horse-battery' },
  });
  const { access_token: userToken } = (await userLogin.json()) as { access_token: string };

  await page.addInitScript((t: string) => {
    window.localStorage.setItem(
      'bb-auth',
      JSON.stringify({ state: { accessToken: t, refreshToken: '' }, version: 0 }),
    );
  }, userToken);

  // 1. Record a cash expense via the /transactions UI.
  await page.goto('/transactions');
  await page.waitForLoadState('networkidle');

  const today = new Date().toISOString().slice(0, 10);
  const dateInput = page.getByLabel(/날짜/);
  await dateInput.fill(today);
  await page.getByLabel(/금액/).fill('12000');
  await page.getByLabel(/지불방식/).selectOption('CASH');
  const merchant = `E2E 가게 ${stamp}`;
  await page.getByLabel(/사용처/).fill(merchant);

  // Pick the 식비 expense category (seeded by admin approval).
  const categorySelect = page.getByLabel(/카테고리/);
  const categoryOption = categorySelect.locator('option').filter({ hasText: '식비' });
  const categoryValue = await categoryOption.getAttribute('value');
  expect(categoryValue).not.toBeNull();
  await categorySelect.selectOption(categoryValue as string);

  const [postEntry] = await Promise.all([
    page.waitForResponse(
      (res) => res.url().endsWith('/entries') && res.request().method() === 'POST',
    ),
    page.getByRole('button', { name: /기록/ }).click(),
  ]);
  expect(postEntry.status()).toBe(201);

  await expect(page.getByText(merchant)).toBeVisible({ timeout: 15_000 });

  // 2. Verify balance sheet: identity holds and page reflects the expense.
  await Promise.all([
    page.waitForResponse((res) => res.url().includes('/reports/balance-sheet')),
    page.goto('/reports/balance-sheet'),
  ]);
  await expect(page.getByText(/항등식 성립/)).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText('-12000.00').first()).toBeVisible();

  // 3. Verify cash flow: operating outflow 12000, reconciled.
  await Promise.all([
    page.waitForResponse((res) => res.url().includes('/reports/cash-flow')),
    page.goto('/reports/cash-flow'),
  ]);
  await expect(page.getByText(/조정 일치/)).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText('12000.00').first()).toBeVisible();
});

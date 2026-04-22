import { test, expect, request as apiRequest } from '@playwright/test';

const API = process.env['E2E_API_BASE'] ?? 'http://localhost:8080/api';
const ADMIN_EMAIL = process.env['INITIAL_ADMIN_EMAIL'] ?? 'admin@example.com';
const ADMIN_PASSWORD = process.env['INITIAL_ADMIN_PASSWORD'] ?? 'change-me-please';

/**
 * Full flow: register -> admin approves -> login -> create account -> see it in the list.
 * Requires the docker-compose stack and an ADMIN seeded via `make admin-seed`
 * (credentials controlled by INITIAL_ADMIN_EMAIL + INITIAL_ADMIN_PASSWORD in .env).
 */
test('approved user can add an ASSET account and see it in the list', async ({ page }) => {
  const stamp = Date.now();
  const email = `e2e-${stamp}@example.com`;
  const ctx = await apiRequest.newContext();

  // 1. Register (PENDING).
  const registerRes = await ctx.post(`${API}/auth/register`, {
    data: { email, password: 'correct-horse-battery', display_name: `E2E ${stamp}` },
  });
  expect(registerRes.status()).toBe(201);
  const { id: userId } = (await registerRes.json()) as { id: number };

  // 2. Admin logs in + approves the user.
  const adminLogin = await ctx.post(`${API}/auth/login`, {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
  });
  expect(adminLogin.status()).toBe(200);
  const { access_token: adminToken } = (await adminLogin.json()) as { access_token: string };

  const approve = await ctx.patch(`${API}/admin/users/${userId}`, {
    data: { status: 'ACTIVE' },
    headers: { Authorization: `Bearer ${adminToken}` },
  });
  expect(approve.status()).toBe(200);

  // 3. User logs in -> store token in localStorage -> visit accounts page.
  const userLogin = await ctx.post(`${API}/auth/login`, {
    data: { email, password: 'correct-horse-battery' },
  });
  expect(userLogin.status()).toBe(200);
  const { access_token: userToken } = (await userLogin.json()) as { access_token: string };

  await page.addInitScript((t: string) => {
    window.localStorage.setItem(
      'bb-auth',
      JSON.stringify({ state: { accessToken: t, refreshToken: '' }, version: 0 }),
    );
  }, userToken);

  await page.goto('/accounts');

  // Seeded by admin approval: default accounts should include 현금.
  await expect(page.getByText('현금')).toBeVisible();

  // 4. Add a new custom account.
  const unique = `E2E 계좌 ${stamp}`;
  await page.getByLabel('이름', { exact: true }).fill(unique);
  await page.getByLabel(/계정과목/).selectOption('ASSET');

  const [createResp, , ] = await Promise.all([
    page.waitForResponse(
      (res) => res.url().endsWith('/accounts') && res.request().method() === 'POST',
    ),
    page.waitForResponse(
      (res) =>
        res.url().includes('/accounts?') === false &&
        res.url().endsWith('/accounts') &&
        res.request().method() === 'GET' &&
        res.status() === 200,
      { timeout: 15_000 },
    ),
    page.getByRole('button', { name: /추가/ }).click(),
  ]);
  expect(createResp.status()).toBe(201);

  await expect(page.getByText(unique)).toBeVisible({ timeout: 10_000 });
});

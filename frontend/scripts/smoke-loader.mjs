import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });
const wait = ms => new Promise(resolve => setTimeout(resolve, ms));

try {
  const context = await browser.newContext({ viewport: { width: 390, height: 800 } });
  const page = await context.newPage();
  await page.route('**/api/v1/auth/me', async route => { await wait(1000); await route.continue(); });
  await page.goto('http://127.0.0.1:5173/dashboard');
  const loader = page.getByRole('status', { name: 'Memuat data' });
  await loader.waitFor({ timeout: 900 });
  assert.equal(await loader.locator('[data-wave-bar]').count(), 5);
  assert.equal(await page.getByText('Memeriksa sesi…').count(), 0);
  assert.equal(await page.getByText('Memuat data…').count(), 0);
  const box = await loader.boundingBox();
  assert.ok(box && Math.abs(box.x + box.width / 2 - 195) < 5);
  assert.ok(box && Math.abs(box.y + box.height / 2 - 400) < 5);
  await page.emulateMedia({ reducedMotion: 'reduce' });
  const duration = await loader.locator('[data-wave-bar]').first().evaluate(element => getComputedStyle(element).animationDuration);
  assert.ok(parseFloat(duration) < 0.01, `reduced motion duration: ${duration}`);
  await loader.waitFor({ state: 'hidden' });
  await page.getByRole('heading', { name: 'Masuk ke maqna-cbt' }).waitFor();

  await page.route('**/api/v1/auth/login', async route => { await wait(1000); await route.continue(); });
  await page.locator('input[autocomplete="username"]').fill('teacher_demo');
  await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await page.getByRole('button', { name: 'Masuk' }).click();
  const actionLoader = page.getByRole('status', { name: 'Memproses permintaan' });
  await actionLoader.waitFor({ timeout: 900 });
  assert.equal(await page.getByText('Memproses permintaan…').count(), 0);
  assert.equal(await page.getByRole('button', { name: 'Masuk' }).count(), 1);
  await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await actionLoader.waitFor({ state: 'hidden' });
  console.log('Loader centered, five wave bars, data/action lifecycle PASS');
  await context.close();
} finally {
  await browser.close();
}

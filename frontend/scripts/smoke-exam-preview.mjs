import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:5173';
const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 860 } });
    const page = await context.newPage();
    await page.goto(base + '/login');
    await page.locator('input[autocomplete="username"]').fill('admin_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
    await page.goto(base + '/exams/301');
    await page.getByRole('heading', { name: 'Pengaturan ujian' }).waitFor();
    await page.getByRole('heading', { name: 'Ringkasan bank soal' }).waitFor();
    assert.equal(await page.getByRole('group', { name: 'Soal', exact: true }).count(), 0, 'bank detail hides legacy question picker');
    await page.getByRole('button', { name: 'Pratinjau tampilan siswa' }).click();
    await page.getByRole('dialog').getByText(/Simulasi saja/).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `no overflow at ${width}px`);
    const next = page.getByRole('dialog').getByRole('button', { name: 'Berikutnya' });
    if (await next.isEnabled()) await next.click();
    await page.getByRole('dialog').getByRole('button', { name: 'Tutup' }).click();
    await page.getByRole('button', { name: 'Pratinjau tampilan siswa' }).click();
    await page.getByRole('dialog').waitFor();
    console.log(`Bank detail and interactive preview ${width}px PASS`);
    await context.close();
  }
} finally { await browser.close(); }

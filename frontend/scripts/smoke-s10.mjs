import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:5173';
const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });
try {
  for (const width of [390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 800 } });
    const page = await context.newPage();
    await page.goto(base + '/login');
    await page.locator('input[autocomplete="username"]').fill('admin_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
    await page.getByRole('heading', { name: 'Token ujian' }).waitFor();
    await page.getByLabel(/Mode token ujian/).first().waitFor();
    await page.goto(base + '/exams');
    await page.getByRole('heading', { name: 'Status massal ujian' }).waitFor();
    const exams = await page.evaluate(async () => (await (await fetch('/api/v1/exams?perPage=100', { credentials: 'same-origin' })).json()).data);
    const exam = exams.find(row => row.status === 'published');
    assert.ok(exam);
    await page.goto(base + '/exams/' + exam.id);
    await page.getByRole('heading', { name: 'Catatan berita acara' }).waitFor();
    for (const label of ['Cetak Kartu', 'Cetak Daftar hadir', 'Cetak Berita acara']) await page.getByRole('link', { name: label, exact: true }).waitFor();
    await page.goto(base + '/school');
    await page.getByRole('heading', { name: 'Logo sekolah' }).waitFor();
    await page.goto(base + '/reports/' + exam.id);
    await page.getByRole('link', { name: 'Unduh jawaban XLSX' }).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `S10 overflow ${width}`);
    assert.equal(await page.locator('[role="alert"]').count(), 0);
    console.log(`S10 admin controls ${width}px PASS`);
    await context.close();
  }
} finally {
  await browser.close();
}

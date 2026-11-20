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
    await page.getByLabel('Status', { exact: true }).selectOption('published');
    await page.getByRole('button', { name: 'Buat token baru' }).first().waitFor();
    for (const [path, heading] of [
      ['/academic/exam-types', 'Jenis ujian'],
      ['/academic/exam-rooms', 'Ruang ujian'],
      ['/imports/teachers', 'Impor guru'],
      ['/imports/subjects', 'Impor mata pelajaran'],
      ['/proctors', 'Pengawas ujian'],
      ['/people/students', 'Data Siswa'],
    ]) {
      await page.goto(base + path);
      await page.getByRole('heading', { name: heading, exact: true }).waitFor();
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `overflow ${width}px ${path}`);
      assert.equal(await page.locator('[role="alert"]').count(), 0, `API error ${path}`);
    }
    await page.getByLabel('Riwayat login').selectOption('yes');
    await page.getByRole('button', { name: /Periksa 0 pilihan/ }).waitFor();
    console.log(`S8 admin dashboard, masters, import, proctor, student list ${width}px PASS`);
    await context.close();
  }
} finally {
  await browser.close();
}

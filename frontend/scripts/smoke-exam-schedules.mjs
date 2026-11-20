import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const browser = await chromium.launch({ executablePath: process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 900 } });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:5174/login');
    await page.locator('input[autocomplete="username"]').fill('admin_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
    await page.goto('http://127.0.0.1:5174/exams');
    await page.getByRole('heading', { name: 'Jadwal Ujian', exact: true }).waitFor();
    await page.getByRole('button', { name: 'Tambah Jadwal' }).click();
    const dialog = page.getByRole('dialog');
    await dialog.getByRole('heading', { name: 'Tambah Jadwal Ujian' }).waitFor();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    assert.equal(overflow, false, `/exams dialog overflows at ${width}px`);
    await dialog.getByRole('button', { name: /Pilih Bank Soal/ }).click();
    const option = dialog.getByRole('option').first();
    await option.waitFor();
    await option.click();
    assert.ok(await dialog.locator('input[aria-label="Jumlah Soal Ditampilkan"]').count() || await dialog.getByLabel(/Jumlah Soal Ditampilkan/).count(), 'bank selection populates question count');
    assert.ok(await dialog.getByLabel('Nama Ujian').inputValue(), 'bank selection populates title');
    await dialog.getByRole('button', { name: 'Batal' }).click();
    console.log(`Exam schedule list and bank picker ${width}px: PASS`);
    await context.close();
  }
} finally {
  await browser.close();
}

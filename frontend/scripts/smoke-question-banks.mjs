import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';

if (process.env.BANK_SCREENSHOTS) mkdirSync(process.env.BANK_SCREENSHOTS, { recursive: true });

const browser = await chromium.launch({ executablePath: process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe', headless: true });
try {
  for (const width of [320, 390, 1280]) {
    const context = await browser.newContext({ viewport: { width, height: 844 } });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:5173/login');
    await page.locator('input[autocomplete="username"]').fill('admin_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
    await page.goto('http://127.0.0.1:5173/questions');
    await page.getByRole('heading', { name: 'Bank soal', exact: true }).waitFor();
    await page.locator('[aria-busy="true"]').waitFor({ state: 'hidden' });
    await page.waitForFunction(() => !document.body.textContent?.includes('Memuat bank soal…'));
    if (process.env.BANK_SCREENSHOTS) await page.screenshot({ path: `${process.env.BANK_SCREENSHOTS}/bank-list-${width}.png`, fullPage: true });
    await page.getByRole('button', { name: 'Buat bank soal' }).click();
    const dialog = page.getByRole('dialog', { name: 'Buat bank soal baru' });
    await page.locator('[aria-busy="true"]').waitFor({ state: 'hidden' });
    if (process.env.BANK_SCREENSHOTS) await page.screenshot({ path: `${process.env.BANK_SCREENSHOTS}/bank-form-${width}.png` });
    await dialog.getByLabel('Jenis ujian').selectOption({ index: 1 });
    await dialog.getByLabel('Mata pelajaran').selectOption({ label: 'Matematika' });
    await dialog.locator('input[type="checkbox"]').first().check();
    const unique = 'UI-BANK-' + Date.now() + '-' + width;
    await dialog.getByLabel('Nama bank soal').fill(unique);
    await dialog.getByRole('button', { name: 'Simpan bank soal' }).click();
    await dialog.waitFor({ state: 'detached' });
    await page.getByRole('link', { name: unique, exact: true }).waitFor();
    await page.getByRole('link', { name: unique, exact: true }).click();
    await page.getByRole('heading', { name: unique }).waitFor();
    await page.locator('[aria-busy="true"]').waitFor({ state: 'hidden' });
    if (process.env.BANK_SCREENSHOTS) await page.screenshot({ path: `${process.env.BANK_SCREENSHOTS}/bank-manage-${width}.png`, fullPage: true });
    await page.getByRole('button', { name: 'Buat soal baru' }).click();
    const editor = page.getByRole('dialog', { name: 'Buat soal baru' });
    await editor.getByRole('heading', { name: 'Tulis soal' }).waitFor();
    await editor.getByRole('textbox', { name: 'Pilihan 1' }).waitFor();
    if (process.env.BANK_SCREENSHOTS) await page.screenshot({ path: `${process.env.BANK_SCREENSHOTS}/question-editor-${width}.png` });
    await editor.getByRole('textbox', { name: 'Pertanyaan' }).fill('Soal bank ' + width + '?');
    const stimulusEditor = editor.locator('.question-editor').first();
    await stimulusEditor.getByRole('button', { name: 'Sisipkan tabel' }).click();
    await editor.locator('.tiptap table').waitFor();
    page.once('dialog', dialog => dialog.accept('x^2'));
    await stimulusEditor.getByRole('button', { name: 'Rumus' }).click();
    await stimulusEditor.getByRole('button', { name: 'Sunting HTML' }).click();
    assert.match(await editor.getByRole('textbox', { name: 'Pertanyaan HTML' }).inputValue(), /<table/i, 'HTML mode keeps tables');
    await stimulusEditor.getByRole('button', { name: 'Tutup HTML' }).click();
    await stimulusEditor.getByRole('button', { name: 'Layar penuh' }).click();
    assert.equal(await stimulusEditor.evaluate(node => node.classList.contains('question-editor--fullscreen')), true);
    await stimulusEditor.getByRole('button', { name: 'Layar penuh' }).click();
    for (const [index, answer] of ['Benar', 'Salah A', 'Salah B', 'Salah C'].entries()) {
      await editor.getByRole('textbox', { name: 'Pilihan ' + (index + 1) }).fill(answer);
    }
    await editor.getByRole('button', { name: 'Simpan draf' }).click();
    await editor.waitFor({ state: 'detached' });
    await page.getByText('Soal bank ' + width + '?').waitFor();
    await page.getByRole('button', { name: 'Buat soal baru' }).click();
    const matchingEditor = page.getByRole('dialog', { name: 'Buat soal baru' });
    await matchingEditor.getByRole('heading', { name: 'Tulis soal' }).waitFor();
    await matchingEditor.getByLabel('Jenis soal').selectOption('matching');
    await matchingEditor.getByRole('textbox', { name: 'Pertanyaan' }).fill('Pasangkan ' + width);
    await matchingEditor.getByRole('button', { name: '+ Tambah baris' }).click();
    await matchingEditor.getByRole('textbox', { name: 'Pernyataan 5' }).fill('Baris sementara');
    await matchingEditor.getByRole('button', { name: 'Hapus pernyataan 5' }).click();
    for (const [index, label] of ['Target A', 'Target B', 'Target C', 'Target D'].entries()) {
      const field = matchingEditor.getByRole('textbox', { name: 'Pilihan pasangan ' + (index + 1) });
      if (index >= 2) await matchingEditor.getByRole('button', { name: '+ Tambah pilihan' }).click();
      await field.fill(label);
    }
    await matchingEditor.getByRole('button', { name: '+ Tambah pilihan' }).click();
    await matchingEditor.getByRole('textbox', { name: 'Pilihan pasangan 5' }).fill('Pengecoh');
    for (let index = 0; index < 4; index++) {
      await matchingEditor.getByRole('textbox', { name: 'Pernyataan ' + (index + 1) }).fill('Pernyataan ' + index);
      await matchingEditor.getByLabel('Kunci pasangan ' + (index + 1)).selectOption(String(index));
    }
    await matchingEditor.getByRole('button', { name: 'Tambah Soal' }).click();
    await matchingEditor.waitFor({ state: 'detached' });
    await page.getByText('Pasangkan ' + width).waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, `horizontal overflow at ${width}px`);
    const bankId = Number(new URL(page.url()).pathname.split('/').pop());
    const archived = await page.evaluate(async id => {
      const csrf = await (await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })).json();
      const response = await fetch(`/api/v1/question-banks/${id}`, { method: 'DELETE', credentials: 'same-origin', headers: { [csrf.data.header]: csrf.data.token } });
      return response.status;
    }, bankId);
    assert.equal(archived, 200, 'archive test bank');
    console.log(`Bank list/create/manage/question ${width}px: PASS`);
    await context.close();
  }
} finally {
  await browser.close();
}

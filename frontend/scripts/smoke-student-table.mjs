import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const baseUrl = process.env.MAQNA_FRONTEND_URL ?? 'http://127.0.0.1:5173';
const browser = await chromium.launch({ executablePath, headless: true });
try {
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  await page.goto(baseUrl + '/login');
  await page.locator('input[autocomplete="username"]').fill('admin_demo');
  await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await page.goto(baseUrl + '/people/students');
  await page.getByRole('heading', { name: 'Data Siswa', exact: true }).waitFor();
  const table = page.getByRole('table', { name: 'Daftar siswa' });
  for (const heading of ['NIS', 'Username', 'Nama Siswa', 'Kelas', 'Status', 'Aksi']) {
    assert.equal(await table.getByRole('columnheader', { name: heading }).count(), 1);
  }
  if (process.env.MAQNA_CAPTURE_UI === '1') {
    await page.getByText(/Menampilkan 1–10 dari/).waitFor();
    await page.locator('div[role="status"][aria-label]').waitFor({ state: 'hidden' });
    await page.screenshot({ path: join(tmpdir(), 'maqna-students-desktop.png'), fullPage: true });
  }
  await page.getByRole('combobox', { name: 'Baris per halaman' }).click();
  await page.getByRole('option', { name: '10' }).click();
  assert.ok((await table.locator('tbody tr').count()) <= 10);
  await page.getByRole('button', { name: 'Berikutnya' }).click();
  await page.getByText(/Menampilkan 11–20 dari/).waitFor();
  await page.getByRole('button', { name: 'Sebelumnya' }).click();
  await page.getByText(/Menampilkan 1–10 dari/).waitFor();
  await page.getByLabel('Cari siswa').fill('NIS-DEMO-01');
  await page.getByRole('button', { name: 'Cari' }).click();
  await table.getByText('student_demo').waitFor();
  await table.getByText('VII A').waitFor();
  assert.equal(await table.locator('tbody tr').count(), 1);

  const username = 'table.' + Date.now();
  const nis = 'TAB-' + Date.now();
  await page.getByRole('button', { name: 'Tambah Siswa' }).click();
  await page.getByRole('dialog').getByLabel('Nama pengguna').fill(username);
  await page.getByRole('dialog').getByLabel('Nama tampilan').fill('Siswa Tabel Uji');
  await page.getByRole('dialog').getByLabel('NIS', { exact: true }).fill(nis);
  await page.getByRole('dialog').getByRole('button', { name: 'Simpan' }).click();
  await page.getByText('Sandi sementara (ditampilkan sekali)').waitFor();
  await page.getByLabel('Cari siswa').fill(nis);
  await page.getByRole('button', { name: 'Cari' }).click();
  await page.waitForFunction(() => document.querySelectorAll('table tbody tr').length === 1);
  const studentRow = table.getByRole('row').filter({ hasText: username });
  await studentRow.getByText('Belum ada kelas').waitFor();
  await studentRow.getByRole('button', { name: 'Ubah ' + username }).click();
  await page.getByRole('dialog').getByLabel('Nama tampilan').fill('Siswa Tabel Diperbarui');
  await page.getByRole('dialog').getByRole('button', { name: 'Simpan' }).click();
  await table.getByRole('row').filter({ hasText: username }).getByText('Siswa Tabel Diperbarui').waitFor();
  await table.getByRole('row').filter({ hasText: username }).getByRole('link', { name: 'Bantu reset sandi ' + username }).click();
  await page.getByRole('heading', { name: 'Bantu reset kata sandi siswa' }).waitFor();
  assert.equal(await page.getByLabel('Nama pengguna siswa').inputValue(), username);

  await page.goto(baseUrl + '/people/students');
  await page.getByRole('heading', { name: 'Data Siswa' }).waitFor();
  await page.getByRole('link', { name: 'Impor' }).click();
  await page.getByRole('heading', { name: 'Impor siswa' }).waitFor();
  for (const width of [320, 390]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto(baseUrl + '/people/students');
    await page.getByRole('heading', { name: 'Data Siswa' }).waitFor();
    await page.getByLabel('Cari siswa').fill('NIS-DEMO-01');
    await page.getByRole('button', { name: 'Cari' }).click();
    await page.locator('article').getByText('NIS-DEMO-01').waitFor();
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, `overflow at ${width}px`);
    assert.equal(await page.locator('table:visible').count(), 0);
    if (width === 320 && process.env.MAQNA_CAPTURE_UI === '1') {
      await page.locator('div[role="status"][aria-label]').waitFor({ state: 'hidden' });
      await page.screenshot({ path: join(tmpdir(), 'maqna-students-mobile.png'), fullPage: true });
    }
  }
  await context.close();
  console.log('Student table API/UI flow and 320/390/1280px layout: PASS');
} finally {
  await browser.close();
}

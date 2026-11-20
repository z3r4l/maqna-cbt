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
    await page.locator('input[autocomplete="username"]').fill('teacher_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
    await page.goto(base + '/questions/tools');
    await page.getByRole('heading', { name: 'Bank soal', exact: true }).waitFor();
    await page.getByRole('link', { name: 'Unduh JSON' }).waitFor();
    await page.getByRole('button', { name: 'Periksa berkas' }).waitFor();
    await page.getByRole('link', { name: 'Unduh paket ZIP' }).waitFor();
    await page.getByRole('link', { name: 'Unduh template Word' }).waitFor();
    await page.getByRole('button', { name: 'Periksa kunci' }).waitFor();
    await page.getByRole('button', { name: 'Periksa paket' }).waitFor();
    await page.goto(base + '/questions/new');
    const types = page.getByLabel('Jenis soal');
    for (const type of ['multi_mcq', 'true_false', 'matching']) {
      await types.selectOption(type);
      await page.getByRole('heading', { name: 'Pratinjau' }).waitFor();
      if (type === 'matching') await page.getByRole('textbox', { name: 'Pilihan pasangan 1' }).waitFor();
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `overflow ${width}px ${type}`);
    }
    const rich = await page.evaluate(async () => {
      const masters = await (await fetch('/api/v1/question-masters', { credentials: 'same-origin' })).json();
      const csrf = await (await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })).json();
      const response = await fetch('/api/v1/questions', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', [csrf.data.header]: csrf.data.token },
        body: JSON.stringify({ subjectId: Number(masters.data.subjects[0].id), body: `<p><strong>Rich S9 ${Date.now()}</strong> $x^2$ <a href="javascript:alert(1)">tautan</a><iframe src="https://evil.example/embed/abcdefghijk"></iframe></p>`,
          questionType: 'mcq', weight: 1, options: [{ body: 'A', isCorrect: true }, { body: 'B', isCorrect: false }], status: 'draft' }) });
      return { status: response.status, body: await response.json() };
    });
    assert.equal(rich.status, 201, JSON.stringify(rich.body));
    await page.goto(base + '/questions/' + rich.body.data.id);
    await page.getByRole('heading', { name: 'Soal dan pratinjau' }).waitFor();
    await page.locator('strong').first().waitFor();
    await page.locator('.katex').first().waitFor();
    assert.equal(await page.locator('iframe').count(), 0);
    assert.equal(await page.locator('a[href^="javascript:"]').count(), 0);
    assert.equal(await page.locator('[role="alert"]').count(), 0);
    console.log(`S9 question editor, JSON controls ${width}px PASS`);
    await context.close();
  }
} finally {
  await browser.close();
}

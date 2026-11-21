import assert from 'node:assert/strict';
import { mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';

const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const server = await createServer({ configFile: 'vite.config.ts', server: { host: '127.0.0.1', port: 0, strictPort: false } });
await server.listen();
const browser = await chromium.launch({ executablePath, headless: true });
const origin = server.resolvedUrls.local[0].replace(/\/$/, '');
const screenshotDir = process.env.RUNNER_INTERACTION_SCREENSHOT_DIR;
if (screenshotDir) mkdirSync(screenshotDir, { recursive: true });

function makeAttempt() {
  return {
    id: 42, examId: 6, examTitle: 'Ujian interaksi', subjectName: 'Bahasa Indonesia', packageCode: 'A', status: 'active',
    startedAt: '2026-11-20 10:00:00', deadlineAt: '2026-11-20 12:00:00', submittedAt: null,
    serverNow: '2026-11-20 10:15:00', revision: 0,
    items: [
      { id: 101, order: 1, body: 'Tentukan Benar atau Salah.', categoryName: 'Bahasa Indonesia', type: 'true_false',
        options: [{ id: 111, body: 'Pernyataan pertama faktual.' }, { id: 112, body: 'Pernyataan kedua memiliki teks panjang untuk memastikan sel tabel membungkus dengan baik pada lebar kecil.' }, { id: 113, body: 'Pernyataan ketiga.' }],
        choices: [], assets: [], selectedOptionId: null, essayText: null, answer: null, marked: false, revision: 0 },
      { id: 102, order: 2, body: 'Pasangkan pernyataan dengan jawaban yang tepat.', categoryName: 'Bahasa Indonesia', type: 'matching',
        options: [{ id: 201, body: 'Teks Prosedur' }, { id: 202, body: 'Teks Anekdot' }, { id: 203, body: 'Teks Eksposisi dengan uraian panjang yang akan membungkus beberapa baris di layar kecil.' }],
        choices: [{ id: 301, body: 'Tujuan — langkah-langkah' }, { id: 302, body: 'Abstraksi — orientasi — krisis — reaksi — koda' }, { id: 303, body: 'Tesis — argumentasi — penegasan ulang' }, { id: 304, body: 'Pilihan pengecoh' }],
        assets: [], selectedOptionId: null, essayText: null, answer: null, marked: false, revision: 0 },
    ],
  };
}

async function openRunner(viewport) {
  const context = await browser.newContext({ viewport });
  const page = await context.newPage();
  const state = { attempt: makeAttempt(), saves: [], failNextSave: false };
  await page.route('**/api/v1/**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const path = url.pathname.replace('/api/v1', '');
    let status = 200;
    let body = { data: {} };
    if (path === '/auth/me') body = { data: { id: 1, username: 'student_demo', email: null, name: 'Siswa Demo', schoolId: 1, roles: ['student'], permissions: [], mustChangePassword: false } };
    else if (path === '/student/attempts/42' && request.method() === 'GET') body = { data: state.attempt };
    else if (path.endsWith('/heartbeat')) body = { data: { serverNow: '2026-11-20 10:15:00', deadlineAt: state.attempt.deadlineAt, eventRecorded: true } };
    else if (path.match(/\/student\/attempts\/42\/items\/\d+/) && request.method() === 'PATCH') {
      const payload = request.postDataJSON();
      if (state.failNextSave) {
        state.failNextSave = false;
        await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ error: { code: 'SMOKE_SAVE_FAILED', message: 'Simpan gagal untuk pengujian.' } }) });
        return;
      }
      state.saves.push(payload);
      const itemId = Number(path.split('/').at(-1));
      state.attempt.items = state.attempt.items.map(item => item.id === itemId ? { ...item, answer: payload.answer === undefined ? item.answer : payload.answer, selectedOptionId: payload.selectedOptionId ?? item.selectedOptionId, revision: item.revision + 1 } : item);
      body = { data: { itemId, revision: state.attempt.items.find(item => item.id === itemId).revision, attemptRevision: state.attempt.revision + 1, selectedOptionId: payload.selectedOptionId, marked: payload.marked, savedAt: '2026-11-20 10:15:00' } };
    } else if (path === '/auth/csrf') body = { data: { header: 'X-CSRF-TOKEN', token: 'smoke-token' } };
    else if (path.endsWith('/submit')) body = { data: { id: 42, status: 'submitted', submittedAt: '2026-11-20 10:15:00', unansweredCount: 0 } };
    else if (request.method() !== 'GET') status = 404;
    await route.fulfill({ status, contentType: 'application/json', body: JSON.stringify(body) });
  });
  await page.goto(`${origin}/attempts/42`);
  await page.getByRole('heading', { name: 'Ujian interaksi' }).waitFor();
  return { context, page, state };
}

try {
  const desktop = await openRunner({ width: 1280, height: 900 });
  assert.equal(await desktop.page.getByRole('table').count(), 1, 'Benar/Salah uses a table on desktop');
  await desktop.page.getByRole('radio', { name: 'Benar untuk pernyataan 1' }).check();
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  await desktop.page.getByRole('radio', { name: 'Salah untuk pernyataan 2' }).check();
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(desktop.state.saves.at(-1).answer, { '111': true, '112': false }, 'Benar/Salah saves partial keyed answers and treats Salah as a normal selection');
  assert.equal(await desktop.page.getByRole('radio', { name: 'Salah untuk pernyataan 2' }).evaluate(input => getComputedStyle(input).accentColor), 'rgb(8, 119, 123)', 'Salah is not styled as an error');
  await desktop.page.reload();
  await desktop.page.getByRole('heading', { name: 'Ujian interaksi' }).waitFor();
  assert.equal(await desktop.page.getByRole('radio', { name: 'Benar untuk pernyataan 1' }).isChecked(), true, 'Benar/Salah answer resumes after reload');
  assert.equal(await desktop.page.getByRole('radio', { name: 'Salah untuk pernyataan 2' }).isChecked(), true, 'partial Salah answer resumes after reload');
  if (screenshotDir) await desktop.page.screenshot({ path: join(screenshotDir, 'true-false-desktop.png'), fullPage: true });
  await desktop.page.getByRole('button', { name: 'Selanjutnya' }).click();
  assert.equal(await desktop.page.getByRole('combobox').count(), 0, 'matching has no dropdowns');
  assert.equal(await desktop.page.getByText('Tarik titik dari pernyataan ke jawaban yang tepat, atau pilih kedua sisi.').count(), 1);
  const leftTwo = desktop.page.getByRole('button', { name: /Pernyataan 2: Teks Anekdot/ });
  const rightTwo = desktop.page.getByRole('button', { name: /Jawaban B: Abstraksi/ });
  await leftTwo.click();
  assert.equal(await leftTwo.getAttribute('aria-pressed'), 'true', 'desktop click selects a statement');
  await rightTwo.click();
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(desktop.state.saves.at(-1).answer, { '202': 302 }, 'desktop click connects the selected statement and answer');
  await desktop.page.getByRole('button', { name: /Lepas pasangan untuk Pernyataan 2/ }).click();
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.equal(desktop.state.saves.at(-1).answer, null, 'desktop clear removes the last pair');
  const leftOne = desktop.page.getByRole('button', { name: /Pernyataan 1: Teks Prosedur/ });
  const rightOne = desktop.page.getByRole('button', { name: /Jawaban A: Tujuan/ });
  const from = await leftOne.boundingBox();
  const to = await rightOne.boundingBox();
  await desktop.page.mouse.move(from.x + from.width / 2, from.y + from.height / 2);
  await desktop.page.mouse.down();
  await desktop.page.mouse.move(to.x + to.width / 2, to.y + to.height / 2, { steps: 8 });
  await desktop.page.mouse.up();
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(desktop.state.saves.at(-1).answer, { '201': 301 }, 'dragging connects the selected endpoints');
  assert.equal(await desktop.page.locator('.matching-answer-lines path').count(), 1, 'saved pair draws a connection');
  const pathData = await desktop.page.locator('.matching-answer-lines path').first().getAttribute('d');
  assert.equal(/M\s+-|C\s+-/.test(pathData ?? ''), false, 'connection coordinates stay inside the visible layout');
  if (screenshotDir) await desktop.page.screenshot({ path: join(screenshotDir, 'matching-desktop.png'), fullPage: true });
  const rightDistractor = desktop.page.getByRole('button', { name: /Jawaban D: Pilihan pengecoh/ });
  await leftTwo.focus();
  await leftTwo.press('Enter');
  await rightDistractor.focus();
  await rightDistractor.press('Enter');
  await desktop.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(desktop.state.saves.at(-1).answer, { '201': 301, '202': 304 }, 'keyboard connects a distractor choice');
  const saveCount = desktop.state.saves.length;
  await desktop.page.getByRole('button', { name: /Pernyataan 3:/ }).click();
  assert.equal(await rightDistractor.getAttribute('aria-disabled'), 'true', 'an occupied choice is announced unavailable');
  await rightDistractor.focus();
  await rightDistractor.press('Enter');
  await desktop.page.waitForTimeout(600);
  assert.equal(desktop.state.saves.length, saveCount, 'an occupied right side cannot be reused');
  assert.equal(await desktop.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'desktop layout has no horizontal overflow');
  for (const width of [320, 390, 768, 1280]) {
    await desktop.page.setViewportSize({ width, height: 900 });
    assert.equal(await desktop.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `no horizontal overflow at ${width}px`);
    assert.equal(await desktop.page.locator('.matching-answer-lines').isVisible(), true, `matching connections remain visible at ${width}px`);
    const columns = await desktop.page.locator('[data-matching-columns] > section').evaluateAll(sections => sections.map(section => section.getBoundingClientRect().left));
    assert.ok(columns[1] > columns[0], `matching uses side-by-side columns at ${width}px`);
    if (width <= 390) {
      const longCard = desktop.page.getByRole('button', { name: /Pernyataan 3: Teks Eksposisi/ });
      assert.ok((await longCard.boundingBox()).height > 48, `long statement wraps at ${width}px`);
      assert.ok(await desktop.page.locator('.matching-answer-lines path').count() >= 2, `saved lines remain visible at ${width}px`);
      if (screenshotDir) await desktop.page.screenshot({ path: join(screenshotDir, `matching-${width}.png`), fullPage: true });
    }
  }
  await desktop.context.close();

  const mobile = await openRunner({ width: 390, height: 844 });
  assert.equal(await mobile.page.getByRole('table').count(), 0, 'Benar/Salah reflows out of the table on mobile');
  if (screenshotDir) await mobile.page.screenshot({ path: join(screenshotDir, 'true-false-mobile.png'), fullPage: true });
  await mobile.page.getByRole('button', { name: 'Selanjutnya' }).click();
  assert.equal(await mobile.page.locator('.matching-answer-lines').isVisible(), true, 'mobile shows matching lines');
  const left = mobile.page.getByRole('button', { name: /Pernyataan 1: Teks Prosedur/ });
  const right = mobile.page.getByRole('button', { name: /Jawaban C: Tesis/ });
  await left.click();
  await right.click();
  await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(mobile.state.saves.at(-1).answer, { '201': 303 });
  assert.equal(await mobile.page.locator('.matching-answer-lines path').count(), 1, 'mobile draws the saved pair');
  await left.click();
  await mobile.page.getByRole('button', { name: /Jawaban A: Tujuan/ }).click();
  await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(mobile.state.saves.at(-1).answer, { '201': 301 }, 'mobile tap changes a pair');
  if (screenshotDir) await mobile.page.screenshot({ path: join(screenshotDir, 'matching-mobile.png'), fullPage: true });
  await mobile.page.getByRole('button', { name: /Lepas pasangan untuk Pernyataan 1/ }).click();
  await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.equal(mobile.state.saves.at(-1).answer, null);
  const touchStart = await mobile.page.getByRole('button', { name: /Pernyataan 2: Teks Anekdot/ }).locator('[data-matching-dot]').boundingBox();
  const touchTarget = await mobile.page.getByRole('button', { name: /Jawaban B: Abstraksi/ }).boundingBox();
  await mobile.page.evaluate(({ start, target }) => {
    const source = document.elementFromPoint(start.x + start.width / 2, start.y + start.height / 2);
    source.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true, pointerType: 'touch', button: 0, clientX: start.x + start.width / 2, clientY: start.y + start.height / 2 }));
    window.dispatchEvent(new PointerEvent('pointermove', { bubbles: true, pointerType: 'touch', clientX: target.x + target.width / 2, clientY: target.y + target.height / 2 }));
  }, { start: touchStart, target: touchTarget });
  await mobile.page.waitForTimeout(50);
  await mobile.page.evaluate(target => window.dispatchEvent(new PointerEvent('pointerup', { bubbles: true, pointerType: 'touch', clientX: target.x + target.width / 2, clientY: target.y + target.height / 2 })), touchTarget);
  await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.deepEqual(mobile.state.saves.at(-1).answer, { '202': 302 }, 'touch drag from the mobile dot connects a pair');
  await mobile.page.reload();
  await mobile.page.getByRole('heading', { name: 'Ujian interaksi' }).waitFor();
  if (await mobile.page.getByRole('button', { name: 'Selanjutnya' }).count()) await mobile.page.getByRole('button', { name: 'Selanjutnya' }).click();
  assert.equal(await mobile.page.locator('.matching-answer-lines path').count(), 1, 'saved mobile pair resumes with its line after reload');
  await mobile.page.getByRole('button', { name: /Lepas pasangan untuk Pernyataan 2/ }).click();
  await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Jawaban tersimpan' }).first().waitFor();
  assert.equal(mobile.state.saves.at(-1).answer, null);
  mobile.state.failNextSave = true;
  await mobile.page.getByRole('button', { name: /Pernyataan 2: Teks Anekdot/ }).click();
  await mobile.page.getByRole('button', { name: /Jawaban A: Tujuan/ }).click();
  await mobile.page.getByRole('alert').waitFor();
  assert.equal(await mobile.page.locator('[role="status"]:visible').filter({ hasText: 'Gagal menyimpan' }).count(), 1, 'failed save is not presented as saved');
  assert.equal(await mobile.page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'mobile layout has no horizontal overflow');
  await mobile.context.close();
  console.log('Runner true/false table, matching drag/tap/keyboard, uniqueness, autosave, clear, resume, failure state: PASS');
} finally {
  await browser.close();
  await server.close();
}

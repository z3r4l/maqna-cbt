import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { join } from 'node:path';

const base = 'http://127.0.0.1:5173';
const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });
async function login(page, username) {
  await page.goto(base + '/login');
  await page.locator('input[autocomplete="username"]').fill(username);
  await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
}
async function call(page, method, path, payload, extraHeaders = {}) {
  return page.evaluate(async ({ method, path, payload, extraHeaders }) => {
    const headers = { Accept: 'application/json', ...extraHeaders };
    if (method !== 'GET') {
      const csrf = await (await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })).json();
      headers[csrf.data.header] = csrf.data.token;
    }
    if (payload !== undefined) headers['Content-Type'] = 'application/json';
    const response = await fetch('/api/v1' + path, { method, credentials: 'same-origin', headers, body: payload === undefined ? undefined : JSON.stringify(payload) });
    return { status: response.status, body: await response.json() };
  }, { method, path, payload, extraHeaders });
}
try {
  const teacherContext = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const teacher = await teacherContext.newPage();
  await login(teacher, 'teacher_demo');
  const masters = await call(teacher, 'GET', '/question-masters');
  const math = masters.body.data.subjects.find(row => row.name === 'Matematika');
  const assignments = await call(teacher, 'GET', '/teacher/assignments');
  const assignment = assignments.body.data.find(row => Number(row.subject_id) === Number(math.id));
  const bank = await call(teacher, 'GET', '/questions?perPage=100&search=S9');
  const ids = ['multi_mcq', 'true_false', 'matching'].map(type => bank.body.data.find(row => row.question_type === type && row.status === 'published')?.id);
  assert.equal(ids.every(Boolean), true, 'published structured fixture questions');
  const now = Date.now();
  const config = { title: 'S9 browser ' + now, subjectId: Number(math.id), semesterId: Number(assignment.semester_id),
    classIds: [Number(assignment.class_group_id)], questionIds: ids.map(Number),
    startAt: new Date(now - 60000).toISOString(), endAt: new Date(now + 3600000).toISOString(),
    durationMinutes: 30, passingGrade: 70, maxAttempts: 1, resultPolicy: 'first', shuffleQuestions: false, shuffleOptions: true };
  const created = await call(teacher, 'POST', '/exams', config);
  assert.equal(created.status, 201, JSON.stringify(created.body));
  const examId = created.body.data.id;
  const preview = await call(teacher, 'GET', '/exams/' + examId + '/preview');
  assert.equal(preview.status, 200);
  assert.equal(JSON.stringify(preview.body).includes('is_correct'), false);
  await teacher.goto(base + '/exams/' + examId);
  await teacher.getByRole('button', { name: 'Pratinjau tampilan siswa' }).click();
  await teacher.getByRole('heading', { name: 'Pratinjau tampilan siswa' }).waitFor();
  const published = await call(teacher, 'POST', '/exams/' + examId + '/publish');
  assert.equal(published.status, 200, JSON.stringify(published.body));
  await teacherContext.close();

  const studentContext = await browser.newContext({ viewport: { width: 390, height: 800 } });
  const student = await studentContext.newPage();
  await login(student, 'student_demo');
  const device = crypto.randomUUID();
  const started = await call(student, 'POST', '/student/exams/' + examId + '/start', {}, { 'X-Attempt-Device': device });
  assert.equal(started.status, 200, JSON.stringify(started.body));
  const attemptId = started.body.data.id;
  await student.evaluate(({ attemptId, device }) => localStorage.setItem('maqna-device-attempt-' + attemptId, device), { attemptId, device });
  await student.goto(base + '/attempts/' + attemptId);
  await student.getByRole('heading', { name: config.title }).waitFor();
  assert.equal(await student.getByText('maqna-cbt', { exact: true }).count(), 0, 'runner hides the application shell');
  await student.getByRole('button', { name: 'Daftar soal' }).click();
  await student.getByRole('heading', { name: 'Daftar Soal' }).waitFor();
  if (process.env.ATTEMPT_SCREENSHOT_DIR) await student.screenshot({ path: join(process.env.ATTEMPT_SCREENSHOT_DIR, 'attempt-sheet-mobile.png'), fullPage: true });
  await student.getByRole('button', { name: 'Akhiri Ujian' }).click();
  await student.getByRole('heading', { name: 'Akhiri Ujian?' }).waitFor();
  if (process.env.ATTEMPT_SCREENSHOT_DIR) await student.screenshot({ path: join(process.env.ATTEMPT_SCREENSHOT_DIR, 'attempt-submit-mobile.png'), fullPage: true });
  await student.getByRole('button', { name: 'Batal' }).click();
  await student.getByRole('heading', { name: config.title }).waitFor();
  await student.getByRole('button', { name: 'Daftar soal' }).click();
  await student.getByRole('button', { name: 'Tutup daftar soal' }).click();
  await student.getByRole('heading', { name: config.title }).waitFor();
  if (process.env.ATTEMPT_SCREENSHOT_DIR) {
    await student.setViewportSize({ width: 940, height: 685 });
    await student.screenshot({ path: join(process.env.ATTEMPT_SCREENSHOT_DIR, 'attempt-desktop.png'), fullPage: true });
    await student.setViewportSize({ width: 390, height: 844 });
    await student.screenshot({ path: join(process.env.ATTEMPT_SCREENSHOT_DIR, 'attempt-mobile.png'), fullPage: true });
  }
  for (const width of [320, 390, 940, 1280]) {
    await student.setViewportSize({ width, height: 800 });
    assert.equal(await student.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, `no horizontal overflow at ${width}px`);
    if (width >= 940) assert.equal(await student.getByRole('heading', { name: 'Daftar Soal' }).isVisible(), true, `desktop question list visible at ${width}px`);
  }
  await student.setViewportSize({ width: 390, height: 800 });
  await student.locator('input[type="checkbox"]').first().check();
  await student.getByText('Jawaban tersimpan').first().waitFor();
  await student.getByRole('button', { name: 'Selanjutnya' }).click();
  await student.getByRole('radio').first().check();
  await student.getByText('Jawaban tersimpan').first().waitFor();
  await student.getByRole('button', { name: 'Selanjutnya' }).click();
  const pairs = student.getByRole('combobox');
  await pairs.nth(0).selectOption({ index: 1 });
  await pairs.nth(1).selectOption({ index: 2 });
  await student.getByText('Jawaban tersimpan').first().waitFor();
  await student.reload();
  await student.getByRole('heading', { name: config.title }).waitFor();
  await student.getByRole('button', { name: 'Selanjutnya' }).click();
  await student.getByRole('button', { name: 'Selanjutnya' }).click();
  assert.notEqual(await student.getByRole('combobox').first().inputValue(), '');
  assert.equal(await student.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  await student.getByRole('button', { name: 'Akhiri', exact: true }).click();
  await student.getByRole('heading', { name: 'Akhiri Ujian?' }).waitFor();
  await student.getByRole('button', { name: 'Batal' }).click();
  await student.getByRole('heading', { name: config.title }).waitFor();
  await student.getByRole('button', { name: 'Akhiri', exact: true }).click();
  await student.getByRole('button', { name: 'Ya, Akhiri' }).click();
  await student.getByRole('heading', { name: 'Jawaban terkumpul' }).waitFor();
  await student.getByRole('link', { name: 'Kembali ke ujian saya' }).waitFor();
  await student.goto(base + '/attempts/999999999');
  await student.getByRole('alert').waitFor();
  await student.getByRole('button', { name: 'Coba lagi' }).click();
  await student.getByRole('alert').waitFor();
  console.log('S9 structured runner autosave, reload, sheet, submit 320/390/940/1280px PASS');
  await studentContext.close();
} finally {
  await browser.close();
}

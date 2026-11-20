import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });
try {
  for (const [width, identity] of [[320, 'student_demo'], [390, 'noemail_demo'], [1280, 'teacher_demo']]) {
    const context = await browser.newContext({ viewport: { width, height: 800 } });
    const page = await context.newPage();
    await page.goto('http://127.0.0.1:5173/login');
    await page.getByRole('heading', { name: 'Masuk ke maqna-cbt' }).waitFor();
    const loginOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    assert.equal(loginOverflow, false, `login horizontal overflow at ${width}px`);
    await page.locator('input[autocomplete="username"]').fill(identity);
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button', { name: 'Masuk' }).click();
    await page.getByRole('heading', { name: /Selamat datang/ }).waitFor({ timeout: 10000 });
    const dashboardOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    assert.equal(dashboardOverflow, false, `dashboard horizontal overflow at ${width}px`);
    await page.goto('http://127.0.0.1:5173/profile');
    await page.getByRole('heading', { name: 'Profil dan keamanan' }).waitFor();
    const profileOverflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    assert.equal(profileOverflow, false, `profile horizontal overflow at ${width}px`);
    console.log(`UI ${width}px: login/dashboard/profile PASS`);
    await context.close();
  }
  const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const page = await context.newPage();
  await page.goto('http://127.0.0.1:5173/login');
  await page.locator('input[autocomplete="username"]').fill('admin_demo');
  await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('button', { name: 'Administrasi' }).click();
  await page.getByRole('navigation', { name: 'Navigasi utama' }).getByRole('link', { name: 'Bantu reset sandi' }).click();
  await page.getByRole('heading', { name: 'Bantu reset kata sandi siswa' }).waitFor();
  console.log('UI admin reset navigation: PASS');
  for (const [path, heading] of [
    ['/school', 'Sekolah'],
    ['/academic/academic-years', 'Tahun ajaran'],
    ['/academic/semesters', 'Semester'],
    ['/academic/grade-levels', 'Tingkat'],
    ['/academic/class-groups', 'Rombel'],
    ['/academic/subjects', 'Mata pelajaran'],
    ['/academic/question-categories', 'Kategori soal'],
    ['/academic/question-topics', 'Topik soal'],
    ['/people/students', 'Data Siswa'],
    ['/imports/students', 'Impor siswa'],
    ['/people/teachers', 'Guru'],
    ['/memberships', 'Rombel siswa'],
    ['/teaching', 'Penugasan guru'],
  ]) {
    await page.goto('http://127.0.0.1:5173' + path);
    await page.getByRole('heading', { name: heading, exact: true }).waitFor({ timeout: 10000 });
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'overflow ' + path);
    assert.equal(await page.locator('[role="alert"]').count(), 0, 'API error ' + path);
  }
  console.log('UI admin academic CRUD pages 1280px: PASS');
  await page.goto('http://127.0.0.1:5173/imports/students');
  await page.getByRole('heading', { name: 'Impor siswa' }).waitFor();
  const importedName = 'uiimp.' + Date.now();
  const importCsv = 'nis,name,username,email,class_group_code\nNIS-' + Date.now() + ',Siswa UI,' + importedName + ',,VII-A\n';
  await page.locator('input[type="file"]').setInputFiles({ name: 'students.csv', mimeType: 'text/csv', buffer: Buffer.from(importCsv) });
  await page.getByRole('button', { name: 'Periksa berkas' }).click();
  await page.getByRole('heading', { name: /Pratinjau #/ }).waitFor();
  page.on('dialog', dialog => void dialog.accept());
  await page.getByRole('button', { name: 'Konfirmasi impor' }).click();
  await page.getByRole('heading', { name: 'Impor selesai' }).waitFor();
  await page.getByText(importedName).last().waitFor();
  console.log('UI student CSV preview/commit 1280px: PASS');
  await context.close();
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const mobilePage = await mobile.newPage();
  await mobilePage.goto('http://127.0.0.1:5173/login');
  await mobilePage.locator('input[autocomplete="username"]').fill('admin_demo');
  await mobilePage.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await mobilePage.getByRole('button', { name: 'Masuk' }).click();
  await mobilePage.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  for (const path of ['/academic/academic-years', '/people/students', '/memberships', '/teaching']) {
    await mobilePage.goto('http://127.0.0.1:5173' + path);
    await mobilePage.locator('h1').waitFor();
    assert.equal(await mobilePage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'mobile overflow ' + path);
  }
  console.log('UI academic CRUD pages 390px: PASS');
  await mobile.close();
  const teacherContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const teacherPage = await teacherContext.newPage();
  await teacherPage.goto('http://127.0.0.1:5173/login');
  await teacherPage.locator('input[autocomplete="username"]').fill('teacher_demo');
  await teacherPage.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await teacherPage.getByRole('button', { name: 'Masuk' }).click();
  await teacherPage.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await teacherPage.goto('http://127.0.0.1:5173/questions');
  await teacherPage.getByRole('heading', { name: 'Bank soal', exact: true }).waitFor();
  await teacherPage.getByRole('link', { name: 'Tulis soal' }).click();
  await teacherPage.getByRole('heading', { name: 'Tulis soal' }).waitFor();
  await teacherPage.locator('select').first().selectOption({ label: 'Matematika' });
  await teacherPage.getByRole('textbox', { name: 'Pertanyaan' }).fill('Berapa $2+2$?');
  await teacherPage.locator('.katex').first().waitFor();
  await teacherPage.getByRole('textbox', { name: 'Pertanyaan' }).fill('Uji $\\href{javascript:alert(1)}{Klik}$');
  assert.equal(await teacherPage.locator('main a[href^="javascript:"], main script').count(), 0, 'unsafe math link or script');
  await teacherPage.getByRole('textbox', { name: 'Pertanyaan' }).fill('Berapa $2+2$?');
  await teacherPage.getByRole('textbox', { name: 'Pilihan 1' }).fill('4');
  await teacherPage.getByRole('textbox', { name: 'Pilihan 2' }).fill('5');
  await teacherPage.getByRole('button', { name: 'Simpan draf' }).click();
  await teacherPage.getByRole('heading', { name: 'Soal dan pratinjau' }).waitFor();
  assert.equal(await teacherPage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'question editor mobile overflow');
  const questionPublished = teacherPage.waitForResponse(response => response.request().method() === 'PATCH' && /\/api\/v1\/questions\/\d+$/.test(new URL(response.url()).pathname));
  await teacherPage.getByRole('button', { name: 'Tambah Soal' }).click();
  const questionPublishResponse = await questionPublished;
  assert.equal(questionPublishResponse.status(), 200, 'question publish response: ' + JSON.stringify(await questionPublishResponse.json()));
  await teacherPage.waitForFunction(() => [...document.querySelectorAll('button')].find(button => button.textContent.includes('Tambah Soal'))?.disabled === true);
  assert.equal(await teacherPage.locator('[role="alert"]').count(), 0, 'question publish error');
  const browserQuestionId = Number(new URL(teacherPage.url()).pathname.split('/').pop());
  console.log('UI teacher question create/publish 390px: PASS');
  await teacherPage.goto('http://127.0.0.1:5173/imports/questions');
  await teacherPage.getByRole('heading', { name: 'Impor soal' }).waitFor();
  await teacherPage.getByRole('link', { name: 'Unduh template CSV' }).waitFor();
  console.log('UI KaTeX and question import 390px: PASS');
  await teacherPage.goto('http://127.0.0.1:5173/exams');
  await teacherPage.getByRole('heading', { name: 'Jadwal Ujian', exact: true }).waitFor();
  assert.equal(await teacherPage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'exam schedule mobile overflow');
  await teacherPage.getByRole('button', { name: 'Tambah Jadwal' }).click();
  await teacherPage.getByRole('heading', { name: 'Tambah Jadwal Ujian' }).waitFor();
  await teacherPage.getByRole('button', { name: /Pilih Bank Soal/ }).waitFor();
  await teacherPage.getByRole('button', { name: 'Batal' }).click();
  const browserExamTitle = 'Ujian browser ' + Date.now();
  const browserExamId = await teacherPage.evaluate(async ({ title, questionId }) => {
    async function api(path, method = 'GET', payload) {
      const csrf = await (await fetch('/api/v1/auth/csrf', { credentials: 'same-origin' })).json();
      const response = await fetch('/api/v1' + path, { method, credentials: 'same-origin', headers: { [csrf.data.header]: csrf.data.token, ...(payload ? { 'Content-Type': 'application/json' } : {}) }, body: payload ? JSON.stringify(payload) : undefined });
      const body = await response.json();
      if (!response.ok) throw new Error(path + ': ' + JSON.stringify(body));
      return body.data;
    }
    const [masters, question] = await Promise.all([api('/exam-masters'), api('/questions/' + questionId)]);
    const group = masters.classes.find(row => row.name === 'VII A');
    const semester = masters.semesters.find(row => Number(row.academic_year_id) === Number(group?.academic_year_id));
    if (!group || !semester) throw new Error('Fixture VII A and active semester are required.');
    const start = new Date(Date.now() - 60000).toISOString();
    const end = new Date(Date.now() + 86400000).toISOString();
    const exam = await api('/exams', 'POST', { title, subjectId: Number(question.subject_id), semesterId: Number(semester.id),
      examTypeId: Number(masters.examTypes[0]?.id ?? 0), startAt: start, endAt: end, durationMinutes: 60, passingGrade: 70,
      maxAttempts: 1, resultPolicy: 'first', shuffleQuestions: false, shuffleOptions: false, classIds: [Number(group.id)],
      questionIds: [Number(questionId)], accessCode: '', reviewEnabled: false, packageMode: 'single', requireAllAnswers: false });
    await api('/exams/' + exam.id + '/publish', 'POST', {});
    return Number(exam.id);
  }, { title: browserExamTitle, questionId: browserQuestionId });
  assert.ok(browserExamId > 0);
  assert.equal(await teacherPage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'exam schedule mobile overflow');
  console.log('UI teacher exam schedule and bank dialog 390px: PASS');
  await teacherPage.goto('http://127.0.0.1:5173/questions/' + browserQuestionId);
  await teacherPage.getByRole('button', { name: 'Buat revisi' }).click();
  await teacherPage.getByRole('heading', { name: 'Revisi versi 2' }).waitFor();
  await teacherPage.getByRole('textbox', { name: 'Pertanyaan' }).fill('Revisi browser ' + Date.now());
  await teacherPage.getByRole('button', { name: 'Ajukan tinjauan' }).click();
  await teacherPage.getByText('Status: review').waitFor();
  const browserVersionId = Number(new URL(teacherPage.url()).pathname.split('/').pop());
  await teacherPage.goto('http://127.0.0.1:5173/monitoring');
  await teacherPage.getByRole('heading', { name: 'Pemantauan ujian' }).waitFor();
  await teacherPage.goto('http://127.0.0.1:5173/analytics/questions');
  await teacherPage.getByRole('heading', { name: 'Analisis soal' }).waitFor();
  console.log('UI teacher revision/proctor/analytics 390px: PASS');
  await teacherContext.close();
  const studentContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const studentPage = await studentContext.newPage();
  studentPage.on('dialog', dialog => void dialog.accept());
  await studentPage.goto('http://127.0.0.1:5173/login');
  await studentPage.locator('input[autocomplete="username"]').fill('student_demo');
  await studentPage.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await studentPage.getByRole('button', { name: 'Masuk' }).click();
  await studentPage.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await studentPage.goto('http://127.0.0.1:5173/my-exams');
  await studentPage.getByRole('heading', { name: browserExamTitle }).waitFor();
  await studentPage.getByRole('heading', { name: browserExamTitle }).locator('..').getByRole('button', { name: 'Mulai atau lanjutkan' }).click();
  await studentPage.getByRole('heading', { name: 'Pengerjaan ujian' }).waitFor();
  await studentPage.getByText('Sistem mencatat status koneksi', { exact: false }).waitFor();
  await studentPage.getByRole('button', { name: 'Peta soal' }).click();
  await studentPage.getByRole('dialog', { name: 'Peta soal' }).waitFor();
  await studentPage.getByRole('button', { name: 'Tutup', exact: true }).click();
  await studentPage.locator('input[type="radio"]').first().check();
  await studentPage.getByText('Tersimpan', { exact: true }).waitFor({ timeout: 10000 });
  await studentPage.getByRole('button', { name: 'Tandai untuk ditinjau' }).click();
  await studentPage.getByText('Tersimpan', { exact: true }).waitFor({ timeout: 10000 });
  await studentPage.reload();
  await studentPage.getByRole('heading', { name: 'Pengerjaan ujian' }).waitFor();
  assert.equal(await studentPage.locator('input[type="radio"]:checked').count(), 1, 'answer survives reload');
  await studentPage.locator('input[type="radio"]').first().focus();
  await studentPage.keyboard.press('ArrowDown');
  await studentPage.getByText('Tersimpan', { exact: true }).waitFor({ timeout: 10000 });
  assert.equal(await studentPage.locator('input[type="radio"]:checked').count(), 1, 'keyboard choice navigation');
  for (const width of [320, 390, 1280]) {
    await studentPage.setViewportSize({ width, height: 844 });
    assert.equal(await studentPage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'runner overflow at ' + width);
  }
  await studentPage.setViewportSize({ width: 390, height: 844 });
  await studentPage.getByRole('button', { name: 'Kumpulkan', exact: true }).click();
  await studentPage.getByRole('heading', { name: 'Jawaban terkumpul' }).waitFor();
  assert.equal(await studentPage.locator('body').innerText().then(text => /nilai\s*:\s*\d+/i.test(text)), false, 'score leaked before release');
  console.log('UI runner autosave/reload/submit 320/390/1280px: PASS');
  await studentPage.goto('http://127.0.0.1:5173/my-results');
  await studentPage.getByRole('heading', { name: 'Hasil saya' }).waitFor();
  await studentPage.getByRole('heading', { name: browserExamTitle }).waitFor();
  assert.equal(await studentPage.getByRole('heading', { name: browserExamTitle }).locator('..').getByText('Menunggu rilis hasil').count(), 1);
  assert.equal(await studentPage.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false, 'results mobile overflow');
  await studentPage.getByRole('heading', { name: browserExamTitle }).locator('..').locator('..').getByRole('link', { name: 'Detail' }).click();
  await studentPage.getByText('Menunggu rilis hasil').waitFor();
  console.log('UI student pending result 390px: PASS');
  await studentContext.close();
  const reportContext = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const reportPage = await reportContext.newPage();
  await reportPage.goto('http://127.0.0.1:5173/login');
  await reportPage.locator('input[autocomplete="username"]').fill('teacher_demo');
  await reportPage.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await reportPage.getByRole('button', { name: 'Masuk' }).click();
  await reportPage.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await reportPage.goto('http://127.0.0.1:5173/reports/' + browserExamId);
  await reportPage.getByRole('heading', { name: browserExamTitle }).waitFor();
  await reportPage.getByText('student_demo').waitFor();
  assert.equal(await reportPage.locator('[role="alert"]').count(), 0, 'report API error');
  console.log('UI teacher result report 1280px: PASS');
  await reportContext.close();
  const reviewerContext = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const reviewerPage = await reviewerContext.newPage();
  await reviewerPage.goto('http://127.0.0.1:5173/login');
  await reviewerPage.locator('input[autocomplete="username"]').fill('admin_demo');
  await reviewerPage.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await reviewerPage.getByRole('button', { name: 'Masuk' }).click();
  await reviewerPage.getByRole('heading', { name: /Selamat datang/ }).waitFor();
  await reviewerPage.goto('http://127.0.0.1:5173/question-versions/' + browserVersionId);
  await reviewerPage.getByRole('button', { name: 'Setujui' }).click();
  await reviewerPage.getByText('Status: approved').waitFor();
  assert.equal(await reviewerPage.locator('[role="alert"]').count(), 0, 'revision review error');
  console.log('UI admin revision approval 1280px: PASS');
  await reviewerContext.close();
} finally {
  await browser.close();
}

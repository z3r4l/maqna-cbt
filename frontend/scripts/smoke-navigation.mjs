import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';

const base = 'http://127.0.0.1:5173';
const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser = await chromium.launch({ executablePath, headless: true });

async function login(page, identity) {
  await page.goto(base + '/login');
  await page.locator('input[autocomplete="username"]').fill(identity);
  await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.getByRole('heading', { name: /Selamat datang/ }).waitFor();
}

try {
  const desktop = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const admin = await desktop.newPage();
  await login(admin, 'admin_demo');
  const desktopNav = admin.getByRole('navigation', { name: 'Navigasi utama' });
  const academic = desktopNav.getByRole('button', { name: 'Data akademik' });
  await academic.waitFor();
  assert.equal(await desktopNav.getByRole('button', { name: /Data akademik|Warga sekolah|Soal|Ujian|Hasil & analisis|Administrasi/ }).count(), 6);
  assert.equal(await academic.getAttribute('aria-expanded'), 'false');
  await academic.click();
  assert.equal(await academic.getAttribute('aria-expanded'), 'true');
  await desktopNav.getByRole('link', { name: 'Tahun ajaran' }).waitFor();
  await desktopNav.getByRole('button', { name: 'Warga sekolah' }).click();
  assert.equal(await academic.getAttribute('aria-expanded'), 'false');
  await admin.goto(base + '/academic/subjects');
  await admin.getByRole('heading', { name: 'Mata pelajaran', exact: true }).waitFor();
  await desktopNav.getByRole('link', { name: 'Mata pelajaran' }).waitFor();
  assert.equal(await academic.getAttribute('aria-expanded'), 'true');
  assert.equal(await desktopNav.getByRole('link', { name: 'Mata pelajaran' }).getAttribute('aria-current'), 'page');
  await admin.reload();
  await desktopNav.getByRole('link', { name: 'Mata pelajaran' }).waitFor();
  assert.equal(await academic.getAttribute('aria-expanded'), 'true');
  assert.equal(await admin.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
  console.log('Navigation admin desktop groups, route reload, and active state PASS');
  await desktop.close();

  for (const width of [320, 390]) {
    const mobile = await browser.newContext({ viewport: { width, height: 800 } });
    const teacher = await mobile.newPage();
    await login(teacher, 'teacher_demo');
    const trigger = teacher.getByRole('button', { name: 'Buka navigasi' });
    await trigger.click();
    const drawer = teacher.getByRole('dialog', { name: 'Navigasi utama' });
    await drawer.waitFor();
    assert.equal(await drawer.getByRole('button', { name: 'Data akademik' }).count(), 0);
    assert.equal(await drawer.getByRole('button', { name: 'Warga sekolah' }).count(), 0);
    const questionGroup = drawer.getByRole('button', { name: 'Soal' });
    await questionGroup.focus();
    await teacher.keyboard.press('Enter');
    assert.equal(await questionGroup.getAttribute('aria-expanded'), 'true');
    await drawer.getByRole('link', { name: 'Bank soal' }).waitFor();
    await teacher.keyboard.press('Escape');
    await drawer.waitFor({ state: 'hidden' });
    assert.equal(await trigger.evaluate(element => document.activeElement === element), true);
    await trigger.click();
    await drawer.getByRole('button', { name: 'Soal' }).click();
    await drawer.getByRole('link', { name: 'Bank soal' }).click();
    await teacher.getByRole('heading', { name: 'Bank soal', exact: true }).waitFor();
    await drawer.waitFor({ state: 'hidden' });
    assert.equal(await teacher.evaluate(() => document.documentElement.scrollWidth > innerWidth), false);
    console.log(`Navigation teacher ${width}px sheet, keyboard, and route PASS`);
    await mobile.close();
  }

  const studentContext = await browser.newContext({ viewport: { width: 390, height: 800 } });
  const student = await studentContext.newPage();
  await login(student, 'student_demo');
  await student.getByRole('button', { name: 'Buka navigasi' }).click();
  const studentNav = student.getByRole('dialog', { name: 'Navigasi utama' });
  assert.equal(await studentNav.getByRole('button', { name: 'Soal' }).count(), 0);
  await studentNav.getByRole('link', { name: 'Ujian saya' }).click();
  await student.getByRole('heading', { name: 'Ujian saya' }).waitFor();
  console.log('Navigation student direct links and role visibility PASS');
  await studentContext.close();
} finally {
  await browser.close();
}

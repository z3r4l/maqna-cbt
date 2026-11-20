import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
const base='http://127.0.0.1:5173';
const executablePath=process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const browser=await chromium.launch({executablePath,headless:true});
try {
  for (const width of [320,390,1280]) {
    const context=await browser.newContext({viewport:{width,height:800}});
    const page=await context.newPage();
    await page.goto(base+'/login');
    await page.locator('input[autocomplete="username"]').fill('admin_demo');
    await page.locator('input[autocomplete="current-password"]').fill('FixtureOnly123!');
    await page.getByRole('button',{name:'Masuk'}).click();
    await page.getByRole('heading',{name:/Selamat datang/}).waitFor();
    await page.getByRole('button',{name:'Gunakan sidebar gelap'}).click();
    assert.equal(await page.getByRole('button',{name:'Gunakan sidebar terang'}).getAttribute('aria-pressed'),'true');
    await page.reload();
    assert.equal(await page.getByRole('button',{name:'Gunakan sidebar terang'}).getAttribute('aria-pressed'),'true');
    await page.goto(base+'/help');
    await page.getByRole('heading',{name:'Administrasi sekolah'}).waitFor();
    await page.goto(base+'/updates');
    await page.getByRole('heading',{name:'Pembaruan'}).waitFor();
    await page.goto(base+'/about');
    await page.getByRole('heading',{name:'Tentang maqna-cbt'}).waitFor();
    await page.goto(base+'/people/teachers');
    await page.getByRole('button',{name:'Izin'}).first().click();
    await page.getByRole('heading',{name:/Izin /}).waitFor();
    await page.goto(base+'/school');
    await page.getByRole('heading',{name:'Batas sesi akun'}).waitFor();
    await page.goto(base+'/analytics/questions');
    const select=page.locator('select').first();
    await select.waitFor();
    const options=await select.locator('option').count();
    if (options>1) { await select.selectOption({index:1}); await page.getByRole('heading',{name:'Matriks jawaban siswa'}).waitFor(); }
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`overflow ${width}`);
    assert.equal(await page.locator('[role="alert"]').count(),0);
    console.log(`S11 admin views ${width}px PASS`);
    await context.close();
  }
  const response=await fetch(base+'/manifest.webmanifest');
  assert.equal(response.status,200);
  const manifest=await response.json();
  assert.equal(manifest.name,'maqna-cbt');
  const sw=await (await fetch(base+'/sw.js')).text();
  assert.ok(!sw.includes('/api/v1'));
  console.log('S11 PWA shell assets PASS');
} finally { await browser.close(); }

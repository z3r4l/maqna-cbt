import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { createServer } from 'vite';

const executablePath = process.env.BROWSER_EXECUTABLE ?? 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';
const server = await createServer({ configFile: 'vite.config.ts', server: { host: '127.0.0.1', port: 0, strictPort: false } });
await server.listen();
const browser = await chromium.launch({ executablePath, headless: true });
const origin = server.resolvedUrls.local[0].replace(/\/$/, '');

async function scenario(handler, run) {
  const context = await browser.newContext();
  const page = await context.newPage();
  const requests = [];
  await page.route('**/api/v1/**', async route => {
    const request = route.request();
    const url = new URL(request.url());
    const relevant = url.pathname.endsWith('/auth/csrf') || request.method() !== 'GET';
    if (!relevant) {
      await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ data: {} }) });
      return;
    }
    requests.push({ method: request.method(), path: url.pathname, headers: request.headers() });
    const response = await handler(request, requests);
    await route.fulfill({ status: response.status, contentType: 'application/json', body: JSON.stringify(response.body) });
  });
  await page.goto(origin);
  await run(page, requests);
  await context.close();
}

try {
  await scenario(async (request, requests) => {
    if (request.url().endsWith('/auth/csrf')) return { status: 200, body: { data: { header: 'X-CSRF-TOKEN', token: `token-${requests.filter(row => row.path.endsWith('/auth/csrf')).length}` } } };
    if (request.method() === 'PATCH' && request.headers()['x-csrf-token'] === 'token-1') return { status: 422, body: { error: { code: 'VALIDATION', message: 'Nama tidak valid.' } } };
    if (request.method() === 'PATCH' && request.headers()['x-csrf-token'] === 'token-2') return { status: 200, body: { data: { id: 1 } } };
    return { status: 500, body: { error: { code: 'UNEXPECTED', message: 'Unexpected request.' } } };
  }, async (page, requests) => {
    const result = await page.evaluate(async () => {
      const { api } = await import('/src/lib/api.ts');
      let firstError = '';
      try { await api.updateProfile('X'); } catch (error) { firstError = error.message; }
      const next = await api.updateProfile('Valid Name');
      return { firstError, next };
    });
    assert.equal(result.firstError, 'Nama tidak valid.');
    assert.deepEqual(result.next, { id: 1 });
    assert.deepEqual(requests.map(row => row.method), ['GET', 'PATCH', 'GET', 'PATCH']);
    assert.equal(requests.at(-1).headers['x-csrf-token'], 'token-2');
  });

  await scenario(async (request, requests) => {
    if (request.url().endsWith('/auth/csrf')) return { status: 200, body: { data: { header: 'X-CSRF-TOKEN', token: `token-${requests.filter(row => row.path.endsWith('/auth/csrf')).length}` } } };
    if (request.headers()['x-csrf-token'] === 'token-1') return { status: 403, body: { error: { code: 'CSRF_INVALID', message: 'Token CSRF tidak berlaku.' } } };
    if (request.headers()['x-csrf-token'] === 'token-2') return { status: 422, body: { error: { code: 'VALIDATION', message: 'Nama tidak valid.' } } };
    return { status: 500, body: { error: { code: 'UNEXPECTED', message: 'Unexpected request.' } } };
  }, async (page, requests) => {
    const error = await page.evaluate(async () => {
      const { api } = await import('/src/lib/api.ts');
      try { await api.updateProfile('X'); return ''; } catch (reason) { return reason.message; }
    });
    assert.equal(error, 'Nama tidak valid.');
    assert.deepEqual(requests.map(row => row.method), ['GET', 'PATCH', 'GET', 'PATCH']);
    assert.equal(requests[1].headers['x-csrf-token'], 'token-1');
    assert.equal(requests[3].headers['x-csrf-token'], 'token-2');
  });

  await scenario(async request => {
    if (request.url().endsWith('/auth/csrf')) return { status: 200, body: { data: { header: 'X-CSRF-TOKEN', token: 'token-1' } } };
    return { status: 403, body: { error: { code: 'FORBIDDEN', message: 'Akses ditolak.' } } };
  }, async (page, requests) => {
    const error = await page.evaluate(async () => {
      const { api } = await import('/src/lib/api.ts');
      try { await api.updateProfile('X'); return ''; } catch (reason) { return reason.message; }
    });
    assert.equal(error, 'Akses ditolak.');
    assert.deepEqual(requests.map(row => row.method), ['GET', 'PATCH']);
  });

  await scenario(async request => {
    if (request.url().endsWith('/auth/csrf')) return { status: 200, body: { data: { header: 'X-CSRF-TOKEN', token: 'token-1' } } };
    return { status: 403, body: { error: { code: 'CSRF_INVALID', message: 'Token CSRF tidak berlaku.' } } };
  }, async (page, requests) => {
    const error = await page.evaluate(async () => {
      const { api } = await import('/src/lib/api.ts');
      try { await api.updateProfile('X'); return ''; } catch (reason) { return reason.message; }
    });
    assert.match(error, /Token CSRF tidak berlaku/);
    assert.deepEqual(requests.map(row => row.method), ['GET', 'PATCH', 'GET', 'PATCH']);
  });

  console.log('CSRF client stale-token recovery, validation token rotation, forbidden no-retry, retry limit: PASS');
} finally {
  await browser.close();
  await server.close();
}

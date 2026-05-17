const { chromium } = require('playwright');

const action = process.argv[2];
const baseUrl = 'http://127.0.0.1:8000';
const executablePath = 'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe';

async function login(page) {
  await page.goto(`${baseUrl}/admin/login`, { waitUntil: 'networkidle' });
  await page.fill('#email', 'qa.admin@kiosk.test');
  await page.fill('#password', 'Password1!');
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'networkidle' }),
    page.click('button[type=submit]'),
  ]);
}

async function run() {
  const browser = await chromium.launch({ headless: true, executablePath });
  const page = await browser.newPage();

  try {
    if (action === 'WTL-001') {
      await login(page);
      await page.goto(`${baseUrl}/staff/queue`, { waitUntil: 'networkidle' });
      const beforeSubmitUrl = page.url();
      await page.locator('input[type=text]').first().fill('QA Browser Valid');
      await page.locator('input[type=tel]').fill('09179000101');
      await page.locator('input[type=number]').fill('2');
      await page.locator('select').selectOption('none');
      await page.getByRole('button', { name: /Add to queue/i }).click();
      await page.waitForTimeout(2000);
      const body = await page.locator('body').innerText();
      console.log(JSON.stringify({ ok: true, body, url: page.url(), beforeSubmitUrl }));
    } else if (action === 'WTL-002') {
      await login(page);
      await page.goto(`${baseUrl}/staff/queue`, { waitUntil: 'networkidle' });
      const beforeSubmitUrl = page.url();
      await page.locator('input[type=text]').first().fill('QA Browser NoPhone');
      await page.locator('input[type=tel]').fill('');
      await page.locator('input[type=number]').fill('3');
      await page.locator('select').selectOption('none');
      await page.getByRole('button', { name: /Add to queue/i }).click();
      await page.waitForTimeout(2000);
      const body = await page.locator('body').innerText();
      console.log(JSON.stringify({ ok: true, body, url: page.url(), beforeSubmitUrl }));
    } else if (action === 'WTL-003') {
      await login(page);
      await page.goto(`${baseUrl}/staff/queue`, { waitUntil: 'networkidle' });
      const beforeSubmitUrl = page.url();
      await page.locator('input[type=text]').first().fill('Invalid123');
      await page.locator('input[type=tel]').fill('09179000103');
      await page.locator('input[type=number]').fill('2');
      await page.locator('select').selectOption('none');
      await page.getByRole('button', { name: /Add to queue/i }).click();
      await page.waitForTimeout(2000);
      const body = await page.locator('body').innerText();
      console.log(JSON.stringify({ ok: true, body, url: page.url(), beforeSubmitUrl }));
    } else if (action === 'FLM-001') {
      await login(page);
      await page.goto(`${baseUrl}/admin/seating-layout`, { waitUntil: 'networkidle' });
      const body = await page.locator('body').innerText();
      console.log(JSON.stringify({ ok: true, title: await page.title(), body, url: page.url() }));
    } else if (action === 'ANL-010') {
      await page.goto(`${baseUrl}/admin/seating-analytics`, { waitUntil: 'networkidle' });
      const body = await page.locator('body').innerText();
      console.log(JSON.stringify({ ok: true, title: await page.title(), body, url: page.url() }));
    } else {
      console.log(JSON.stringify({ ok: false, error: `Unknown action ${action}` }));
      process.exitCode = 1;
    }
  } finally {
    await browser.close();
  }
}

run().catch((error) => {
  console.log(JSON.stringify({ ok: false, error: String(error && error.stack || error) }));
  process.exitCode = 1;
});

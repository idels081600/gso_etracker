import { chromium } from 'playwright';
import fs from 'node:fs';

const baseUrl = process.env.TEST_BASE_URL;
const outputPath = process.env.TEST_BROWSER_OUTPUT || 'outputs/browser-smoke-results.json';
const testPassword = process.env.TEST_ADMIN_PASSWORD;
if (!baseUrl || !testPassword) throw new Error('TEST_BASE_URL and TEST_ADMIN_PASSWORD are required.');

const browserCandidates = [
  process.env.PLAYWRIGHT_CHROME_PATH,
  'C:/Program Files/Google/Chrome/Application/chrome.exe',
  'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
  'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
].filter(Boolean);
const executablePath = browserCandidates.find(candidate => fs.existsSync(candidate));
if (!executablePath) throw new Error('Chrome or Edge is required for the browser smoke test.');
const browser = await chromium.launch({ headless: true, executablePath });
const results = [];
const add = (name, passed, detail = '') => {
  results.push({ area: 'Browser', name, passed, detail, severity: 'required' });
  process.stdout.write(`${passed ? '[PASS]' : '[FAIL]'} Browser — ${name}${detail ? `: ${detail}` : ''}\n`);
};

try {
  const context = await browser.newContext();
  const page = await context.newPage();
  const consoleErrors = [];
  const pageErrors = [];
  page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
  page.on('pageerror', error => pageErrors.push(error.message));
  await page.goto(`${baseUrl}/Logi_login.php`, { waitUntil: 'domcontentloaded' });
  await page.fill('#username', 'ADMIN_SAP');
  await page.fill('#password', testPassword);
  await Promise.all([
    page.waitForURL(/Logi_Sys_Dashboard\.php/),
    page.click('button[type="submit"]'),
  ]);
  add('ADMIN_SAP login reaches dashboard', /Logi_Sys_Dashboard\.php/.test(page.url()), page.url());

  const adminPages = [
    'Logi_inventory.php',
    'Logi_transactions.php',
    'Logi_app_req.php',
    'Logi_manage_office.php',
    'Logi_scanner.php',
  ];
  for (const adminPage of adminPages) {
    const response = await page.goto(`${baseUrl}/${adminPage}`, { waitUntil: 'domcontentloaded' });
    const tokenPresent = await page.locator('meta[name="logisys-csrf"]').count() === 1;
    add(`${adminPage} renders with a CSRF token`, response?.status() === 200 && tokenPresent, `status=${response?.status()}, token=${tokenPresent}`);
  }

  await page.goto(`${baseUrl}/Logi_transactions.php`, { waitUntil: 'domcontentloaded' });
  const guardedRequest = await page.evaluate(async () => {
    const response = await fetch('Logi_stock_out.php', { method: 'POST', body: new URLSearchParams() });
    return { status: response.status, body: await response.json() };
  });
  add('Shared browser transport attaches CSRF to legacy AJAX', guardedRequest.status !== 403 && !String(guardedRequest.body?.message || '').includes('security token'), `status=${guardedRequest.status}`);

  for (const viewport of [{ width: 1440, height: 1000 }, { width: 1024, height: 900 }, { width: 390, height: 844 }]) {
    await page.setViewportSize(viewport);
    await page.goto(`${baseUrl}/Logi_ib_monitoring.php`, { waitUntil: 'networkidle' });
    const titleVisible = await page.getByRole('heading', { name: 'IB Monitoring' }).isVisible();
    const records = await page.locator('.ib-record').count();
    const expandedIbBodies = await page.locator('.ib-record > .collapse.show').count();
    const expandedOfficeTables = await page.locator('.ib-office-table.show').count();
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    add(`${viewport.width}px renders IB Monitoring`, titleVisible && records > 0, `records=${records}`);
    add(`${viewport.width}px defaults IB and office details to minimized`, expandedIbBodies === 0 && expandedOfficeTables === 0, `ib_open=${expandedIbBodies}, office_open=${expandedOfficeTables}`);
    add(`${viewport.width}px has no page-level horizontal overflow`, overflow <= 2, `overflow=${overflow}px`);
    await page.screenshot({ path: `outputs/ib-monitoring-${viewport.width}.png`, fullPage: true });
  }
  add('No uncaught browser errors', pageErrors.length === 0, pageErrors.join(' | '));
  add('No browser console errors', consoleErrors.length === 0, consoleErrors.join(' | '));
  await context.close();

  const requesterContext = await browser.newContext();
  const requesterPage = await requesterContext.newPage();
  const requesterErrors = [];
  requesterPage.on('pageerror', error => requesterErrors.push(error.message));
  await requesterPage.goto(`${baseUrl}/Logi_login.php`, { waitUntil: 'domcontentloaded' });
  await requesterPage.fill('#username', 'ADMIN');
  await requesterPage.fill('#password', testPassword);
  await Promise.all([
    requesterPage.waitForURL(/Logi_my_req\.php/),
    requesterPage.click('button[type="submit"]'),
  ]);
  for (const requesterPath of ['Logi_my_req.php', 'Logi_req.php']) {
    const response = await requesterPage.goto(`${baseUrl}/${requesterPath}`, { waitUntil: 'domcontentloaded' });
    add(`${requesterPath} renders for an office account`, response?.status() === 200, `status=${response?.status()}`);
  }
  add('Requester pages have no uncaught browser errors', requesterErrors.length === 0, requesterErrors.join(' | '));
  await requesterContext.close();
} finally {
  await browser.close();
}

fs.mkdirSync(new URL('../outputs/', import.meta.url), { recursive: true });
fs.writeFileSync(outputPath, JSON.stringify({
  generated_at: new Date().toISOString(),
  passed: results.filter(result => result.passed).length,
  failed: results.filter(result => !result.passed).length,
  results,
}, null, 2));
process.exit(results.some(result => !result.passed) ? 1 : 0);

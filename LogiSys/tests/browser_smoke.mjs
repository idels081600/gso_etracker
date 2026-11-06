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
  page.setDefaultTimeout(60000);
  page.setDefaultNavigationTimeout(120000);
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
    await page.goto(`${baseUrl}/Logi_ib_monitoring.php`, { waitUntil: 'domcontentloaded' });
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
  await page.setViewportSize({ width: 1440, height: 1000 });
  await page.goto(`${baseUrl}/Logi_ib_monitoring.php`, { waitUntil: 'domcontentloaded' });
  const keepAliveResult = await page.evaluate(async () => {
    const response = await fetch('Logi_session_keepalive.php', { cache: 'no-store' });
    return { status: response.status, body: await response.json() };
  });
  add('IB Monitoring keepalive refreshes the signed-in session', keepAliveResult.status === 200 && keepAliveResult.body?.active === true, `status=${keepAliveResult.status}`);
  await page.locator('#createIbBtn').click();
  const temporaryDraftButtonVisible = await page.locator('#saveTemporaryIbDraft').isVisible();
  add('IB editor shows a temporary draft action', temporaryDraftButtonVisible, `visible=${temporaryDraftButtonVisible}`);
  const cepidoOfficeAvailable = await page.locator('.editor-office-group').first().locator('.group-office option').allTextContents().then(options => options.some(option => option.trim() === 'CEPIDO'));
  add('CEPIDO is available in the IB office dropdown', cepidoOfficeAvailable, `available=${cepidoOfficeAvailable}`);
  const temporaryDraftIbNo = `TEMP-RECOVERY-${Date.now()}`;
  await page.locator('#ibNumber').fill(temporaryDraftIbNo);
  await page.locator('.editor-office-group').first().locator('.group-office').selectOption({ index: 1 });
  await page.locator('.editor-office-group').first().locator('.group-description').fill('Temporary draft browser test');
  const firstCatalogItem = await page.evaluate(() => window.ibPageData.items[0]);
  await page.locator('.editor-item-row').first().locator('.item-search').fill(`${firstCatalogItem.item_name} — ${firstCatalogItem.item_no}`);
  await page.locator('.editor-item-row').first().locator('.item-search').dispatchEvent('input');
  await page.locator('.editor-item-row').first().locator('.item-qty').fill('3');
  await page.locator('.editor-item-row').first().locator('.item-price').fill('12.50');
  await page.locator('#saveTemporaryIbDraft').click();
  await page.waitForFunction(() => document.getElementById('ibTemporaryDraftStatus')?.textContent.includes('Temporary draft saved'));
  const storedTemporaryDraft = await page.evaluate(() => Object.keys(localStorage).some(key => key.startsWith('logisys:ib-editor-draft:v1:')));
  await page.reload({ waitUntil: 'domcontentloaded' });
  const recoveryVisible = await page.locator('#ibDraftRecovery').isVisible();
  await page.screenshot({ path: 'outputs/ib-draft-recovery-1440.png', fullPage: true });
  await page.locator('#ibDraftRecovery').getByRole('button', { name: 'Restore draft' }).click();
  await page.locator('#ibEditorModal').waitFor({ state: 'visible' });
  await page.waitForTimeout(500);
  await page.screenshot({ path: 'outputs/ib-draft-restored-1440.png', fullPage: true });
  const restoredNumber = await page.locator('#ibNumber').inputValue();
  const restoredDescription = await page.locator('.editor-office-group').first().locator('.group-description').inputValue();
  const restoredQuantity = await page.locator('.editor-item-row').first().locator('.item-qty').inputValue();
  const restoredPrice = await page.locator('.editor-item-row').first().locator('.item-price').inputValue();
  await page.locator('#addOfficeGroup').click();
  const restoredGroupIds = await page.locator('.editor-group-body').evaluateAll(elements => elements.map(element => element.id));
  const uniqueRestoredGroupIds = new Set(restoredGroupIds).size === restoredGroupIds.length;
  add('Unsaved IB form is restored after reload', storedTemporaryDraft && recoveryVisible && restoredNumber === temporaryDraftIbNo && restoredDescription === 'Temporary draft browser test' && restoredQuantity === '3' && restoredPrice === '12.50' && uniqueRestoredGroupIds, `stored=${storedTemporaryDraft}, recovery=${recoveryVisible}, groups=${restoredGroupIds.length}`);
  await page.locator('.editor-office-group').nth(1).locator('.remove-group').click();
  page.once('dialog', dialog => dialog.accept());
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('#saveDraftBtn').click(),
  ]);
  const temporaryDraftCleared = await page.evaluate(() => !Object.keys(localStorage).some(key => key.startsWith('logisys:ib-editor-draft:v1:')));
  add('Successful IB save clears the temporary browser draft', temporaryDraftCleared, `cleared=${temporaryDraftCleared}`);
  await page.setViewportSize({ width: 390, height: 844 });
  await page.locator('#createIbBtn').click();
  await page.locator('#ibEditorModal').waitFor({ state: 'visible' });
  await page.waitForTimeout(500);
  const temporarySaveVisible = await page.getByRole('button', { name: 'Save Temporary Draft' }).isVisible();
  const editorOverflow = await page.locator('#ibEditorModal .modal-content').evaluate(element => Math.max(0, element.scrollWidth - element.clientWidth));
  add('390px IB editor keeps temporary save available without horizontal overflow', temporarySaveVisible && editorOverflow <= 2, `button=${temporarySaveVisible}, overflow=${editorOverflow}px`);
  await page.screenshot({ path: 'outputs/ib-draft-editor-390.png', fullPage: true });
  await page.locator('#ibEditorModal [data-bs-dismiss="modal"]').first().click();
  for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
    await page.setViewportSize(viewport);
    await page.goto(`${baseUrl}/Logi_ib_monitoring.php`, { waitUntil: 'domcontentloaded' });
    const completedOfficeRecord = page.locator('.ib-record:has(.ib-office-fulfilled)').first();
    const fullyDeliveredOffices = await page.locator('.ib-office-fulfilled').count();
    if (fullyDeliveredOffices > 0) await completedOfficeRecord.locator('.ib-record-head').click();
    add(`${viewport.width}px identifies fully delivered office groups`, fullyDeliveredOffices > 0, `indicators=${fullyDeliveredOffices}`);
    await page.screenshot({ path: `outputs/ib-fully-delivered-${viewport.width}.png`, fullPage: true });
    const activeRecord = page.locator('.ib-record:has(.add-active-items)').first();
    if (!await activeRecord.locator(':scope > .collapse.show').count()) await activeRecord.locator('.ib-record-head').click();
    await activeRecord.locator('.add-active-items').click();
    await page.locator('input[name="activeAddMode"][value="new"]').check();
    const newOfficeVisible = await page.locator('#activeNewOffice').isVisible();
    const descriptionVisible = await page.locator('#activeNewDescription').isVisible();
    const itemRows = await page.locator('#activeItemRows .editor-item-row').count();
    const modalOverflow = await page.locator('#activeItemsModal .modal-content').evaluate(element => Math.max(0, element.scrollWidth - element.clientWidth));
    add(`${viewport.width}px can open the new Active IB office-group form`, newOfficeVisible && descriptionVisible && itemRows === 1, `office=${newOfficeVisible}, description=${descriptionVisible}, item_rows=${itemRows}`);
    add(`${viewport.width}px Active IB office-group form has no horizontal overflow`, modalOverflow <= 2, `overflow=${modalOverflow}px`);
    await page.screenshot({ path: `outputs/ib-active-group-${viewport.width}.png`, fullPage: true });
    await page.locator('#activeItemsModal [data-bs-dismiss="modal"]').first().click();
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

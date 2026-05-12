import { test, expect } from '@playwright/test';

const baseUrl = (process.env.MRN_QA_BASE_URL || '').replace(/\/$/, '');
const samplePath = process.env.MRN_QA_SAMPLE_PATH || '/sample-page/';
const smokeScope = process.env.MRN_QA_SMOKE_SCOPE || 'public';
const adminUser = process.env.MRN_QA_ADMIN_USER || '';
const adminPass = process.env.MRN_QA_ADMIN_PASS || '';
const ignoreRegexEnv = process.env.MRN_QA_PLAYWRIGHT_IGNORE_REGEX || '';

if (!baseUrl) {
  test.fail(true, 'MRN_QA_BASE_URL must be set for Playwright smoke checks.');
}

function parseIgnoreRegexes() {
  return ignoreRegexEnv
    .split(',')
    .map((v) => v.trim())
    .filter(Boolean)
    .map((pattern) => {
      try {
        return new RegExp(pattern, 'i');
      } catch {
        return null;
      }
    })
    .filter(Boolean);
}

function shouldIgnore(message, ignoreRegexes) {
  return ignoreRegexes.some((regex) => regex.test(message));
}

function attachIssueTracking(page, ignoreRegexes) {
  const issues = {
    consoleErrors: [],
    pageErrors: [],
    requestFailures: [],
  };

  page.on('console', (msg) => {
    if (msg.type() !== 'error') {
      return;
    }
    const location = msg.location();
    const sourceUrl = location?.url || '';
    const text = msg.text() || '';
    const combined = sourceUrl ? `${text} @ ${sourceUrl}` : text;
    if (!shouldIgnore(combined, ignoreRegexes)) {
      issues.consoleErrors.push(combined);
    }
  });

  page.on('pageerror', (err) => {
    const text = String(err?.message || err || 'Unknown page error');
    if (!shouldIgnore(text, ignoreRegexes)) {
      issues.pageErrors.push(text);
    }
  });

  page.on('requestfailed', (request) => {
    const text = `${request.method()} ${request.url()} (${request.failure()?.errorText || 'failed'})`;
    if (!shouldIgnore(text, ignoreRegexes)) {
      issues.requestFailures.push(text);
    }
  });

  return issues;
}

function assertNoIssues(issues, label) {
  expect(issues.consoleErrors, `${label} console errors`).toEqual([]);
  expect(issues.pageErrors, `${label} runtime errors`).toEqual([]);
  expect(issues.requestFailures, `${label} failed requests`).toEqual([]);
}

test('public: homepage loads without runtime errors', async ({ page }) => {
  const ignoreRegexes = parseIgnoreRegexes();
  const issues = attachIssueTracking(page, ignoreRegexes);

  const response = await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
  expect(response, 'homepage response exists').not.toBeNull();
  expect(response.status(), 'homepage status').toBeLessThan(400);

  await page.waitForTimeout(1200);
  assertNoIssues(issues, 'homepage');
});

test('public: sample page loads without runtime errors', async ({ page }) => {
  const ignoreRegexes = parseIgnoreRegexes();
  const issues = attachIssueTracking(page, ignoreRegexes);

  const response = await page.goto(`${baseUrl}${samplePath.startsWith('/') ? samplePath : `/${samplePath}`}`, {
    waitUntil: 'domcontentloaded',
  });

  expect(response, 'sample response exists').not.toBeNull();
  expect(response.status(), 'sample status').toBeLessThan(400);
  await page.waitForTimeout(1200);

  assertNoIssues(issues, 'sample page');
});

test('full: wp-admin login works when credentials are provided', async ({ page }) => {
  test.skip(smokeScope !== 'full', 'Only runs in full smoke scope.');
  test.skip(!adminUser || !adminPass, 'Admin credentials not provided.');

  const ignoreRegexes = parseIgnoreRegexes();
  const issues = attachIssueTracking(page, ignoreRegexes);

  const loginResponse = await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'domcontentloaded' });
  expect(loginResponse, 'login response exists').not.toBeNull();
  expect(loginResponse.status(), 'login status').toBeLessThan(400);

  await page.fill('#user_login', adminUser);
  await page.fill('#user_pass', adminPass);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#wp-submit'),
  ]);

  await expect(page).toHaveURL(/\/wp-admin\//);
  assertNoIssues(issues, 'admin login flow');
});

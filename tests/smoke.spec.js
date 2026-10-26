// Release smoke suite — green, or no release.
// Runs against WordPress Playground with the plugin active and a "Plan a Visit"
// page seeded with service times, a phone number and a Church Center link
// (tests/blueprint.json). The blueprint's test-only mu-plugin resets the plugin
// to its just-activated state, so the first-visit scan runs on every pass.
const { test, expect } = require('@playwright/test');

// PHP problems surface as text in the page body, not HTTP errors.
const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

const SETTINGS = '/wp-admin/options-general.php?page=church-home-screen';

async function expectClean(page) {
  const body = await page.locator('body').innerText();
  expect(body).not.toMatch(PHP_ERRORS);
}

// One row of the scan review: its label, value and whether it starts checked.
async function expectFinding(review, label, value, checked) {
  const row = review
    .locator('.chs-findings li')
    .filter({ has: review.page().locator('.f-label', { hasText: new RegExp(`^${label}$`) }) })
    .filter({ hasText: value });
  await expect(row, `${label} = ${value}`).toHaveCount(1);
  const box = row.locator('input[type="checkbox"]');
  if (checked) await expect(box).toBeChecked();
  else await expect(box).not.toBeChecked();
}

// The flow is one church's first afternoon with the plugin: activate, open
// settings (auto-scan), keep the findings, edit, then open the page.
test.describe.configure({ mode: 'serial' });

test.beforeAll(async ({ browser }) => {
  const page = await browser.newPage({ storageState: 'test-results/.auth/admin.json' });
  await page.goto('/wp-admin/?chs_test_reset=1');
  await expect(page.locator('body')).toContainText('chs reset');
  await page.close();
});

test('activation turns the plugin on and points to setup', async ({ page }) => {
  // Deactivate and reactivate through the UI so the activation hook really runs
  // (it registers the route and queues the welcome notice).
  await page.goto('/wp-admin/plugins.php');
  const row = page.locator('tr[data-plugin="comms-church-home-screen/comms-church-home-screen.php"]');
  await row.getByRole('link', { name: /^Deactivate/ }).click();
  await expect(row).toHaveClass(/\binactive\b/);
  await row.getByRole('link', { name: /^Activate/ }).click();
  await expect(page.locator('#message')).toContainText('activated');
  await expectClean(page);
  await expect(row).toHaveClass(/\bactive\b/);
  await expect(row.getByRole('link', { name: 'Settings' })).toBeVisible();
  await expect(row.getByRole('link', { name: 'Check for updates' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Set up your home screen' })).toBeVisible();
});

test('first visit to settings scans the site and shows the review', async ({ page }) => {
  await page.goto(SETTINGS);
  await expectClean(page);
  const review = page.locator('.chs-review');
  await expect(review).toContainText('We looked through your site to get you started');
  // The seeded page should give the scanner something in each of these.
  await expect(review).toContainText('555-0100');
  await expect(review).toContainText('harborchurch');
  await expect(review.locator('input[name="chs_pick[]"]:checked').first()).toBeVisible();

  // Bricks-style cases from the blueprint's fixture: the header logo's name, the
  // palette's variable colors, and service names written after the time.
  await expectFinding(review, 'Church name', 'Harbor Community Church', true);
  await expectFinding(review, 'Brand color', '#0066ff', true);
  await expectFinding(review, 'Sunday · Classic gathering', '8:30 AM', true);
  await expectFinding(review, 'Sunday · Contemporary gathering', '10:45 AM', true);
  // A specific name before the time still wins; in "11 AM | Bible study 10 AM" the
  // name belongs to 10 AM; "9:00 & 11:00 AM" share the AM; only main services start checked.
  await expectFinding(review, 'Sunday · Sunday School', '9:15 AM', false);
  await expectFinding(review, 'Sunday · Bible Study', '10:00 AM', false);
  await expectFinding(review, 'Sunday · Worship', '9:00 AM', true);
  await expectFinding(review, 'Sunday · Worship', '11:00 AM', true);
  // A footer that runs "Sun 11 AM" into "ADDRESS 212 Peach Bottom Road" (GCCWS):
  // the address starts at the house number, not at the service time.
  await expectFinding(review, 'Address', '212 Peach Bottom Road, Willow Street, PA 17584', true);
  await expect(review).not.toContainText('11 AM ADDRESS');
  await expectFinding(review, 'Town and state', 'Willow Street, PA', true);

  await page.getByRole('button', { name: 'Use selected' }).click();
  await expect(page.locator('.notice-success')).toContainText(/Filled in \d+ settings? from your site/);
  await expectClean(page);
  // The scan runs once; the settings form is what comes up from now on.
  await expect(page.locator('.chs-review')).toHaveCount(0);
  await expect(page.locator('#chs-form')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Set up your home screen' })).toHaveCount(0);
});

test('settings save and persist', async ({ page }) => {
  await page.goto(SETTINGS);
  const name = page.locator('input[name="chs_settings[church_name]"]');
  await name.fill('Harbor Church Smoke');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
  await expectClean(page);
  await expect(page.locator('input[name="chs_settings[church_name]"]')).toHaveValue('Harbor Church Smoke');
});

test('/links renders and swipe up unlocks it', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const res = await page.goto('/links/');
  expect(res.status()).toBe(200);
  await expectClean(page);

  const screen = page.locator('#screen');
  const lock = page.locator('#lock');
  await expect(lock).toContainText('Harbor Church Smoke');
  await expect(page.locator('#lockTime')).not.toBeEmpty();
  await expect(page.locator('#home')).toHaveAttribute('inert', '');

  // Swipe from low on the lock screen to near the top, like a thumb would.
  const box = await lock.boundingBox();
  const x = box.x + box.width / 2;
  await page.mouse.move(x, box.y + box.height * 0.7);
  await page.mouse.down();
  await page.mouse.move(x, box.y + box.height * 0.2, { steps: 12 });
  await page.mouse.up();

  await expect(screen).toHaveClass(/\bunlocked\b/);
  await expect(page.locator('#home')).not.toHaveAttribute('inert');
  await expect(page.locator('#home nav.apps')).toBeVisible();
});

test('Planning Center source lists only Church Center events', async ({ page }) => {
  // The blueprint's fake API (chs-test-pco.php) stands in for Planning Center.
  await page.goto(SETTINGS);
  await page.locator('input[name="chs_settings[events_source]"][value="pco"]').check();
  await page.locator('input[name="chs_settings[pco_app_id]"]').fill('wrong-id');
  await page.locator('input[name="chs_settings[pco_secret]"]').fill('wrong-secret');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  // A bad key says so on the settings page instead of failing silently.
  await expect(page.locator('.chs-pco-status.is-error')).toContainText('Invalid Application ID or Secret.');

  await page.locator('input[name="chs_settings[pco_app_id]"]').fill('test-app-id');
  await page.locator('input[name="chs_settings[pco_secret]"]').fill('test-secret');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('.chs-pco-status.is-ok')).toContainText('1 event is shown in Church Center.');
  await expectClean(page);
  // The secret is never printed back into the page.
  const secret = page.locator('input[name="chs_settings[pco_secret]"]');
  await expect(secret).toHaveValue('');
  await expect(secret).toHaveAttribute('placeholder', /Saved/);
  expect(await page.content()).not.toContain('test-secret');

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/links/');
  await expectClean(page);
  const html = await page.content();
  expect(html).not.toContain('test-secret');
  // Lock screen shows the next Church Center event.
  await expect(page.locator('#lock')).toContainText('Grief Care');

  await page.locator('#unlockBtn').click();
  await page.locator('#home button[data-open="events"]').click();
  const sheet = page.locator('#sheet');
  await expect(sheet).toContainText('Grief Care');
  // Place name only, like Church Center: no street address.
  await expect(sheet).toContainText('Fellowship Hall');
  await expect(sheet).not.toContainText('123 Main');
  // Only approved events that Church Center shows.
  await expect(sheet).not.toContainText('Pending Picnic');
  await expect(sheet).not.toContainText('Elder Meeting');
  await expect(sheet.locator('a.ev').first()).toHaveAttribute('href', /harborchurch\.churchcenter\.com\/calendar\/event\//);
});

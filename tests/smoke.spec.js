// Release smoke suite — green, or no release.
// Runs against WordPress Playground with the plugin active and pages seeded with
// service times, contact details, a Church Center link, a bulletin page and a
// sermon archive (tests/blueprint.json). The blueprint's test-only mu-plugins reset
// the plugin to its just-activated state, so the first-visit scan runs on every pass.
const { test, expect } = require('@playwright/test');

// PHP problems surface as text in the page body, not HTTP errors.
const PHP_ERRORS = /Fatal error|Parse error|Warning: |Notice: |Deprecated: /;

const SETTINGS = '/wp-admin/options-general.php?page=church-links';

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
  await page.goto('/wp-admin/?chs_test_watch_reset=1');
  await expect(page.locator('body')).toContainText('chs watch reset');
  await page.close();
});

test('activation turns the plugin on and points to setup', async ({ page }) => {
  // Deactivate and reactivate through the UI so the activation hook really runs
  // (it registers the route and queues the welcome notice).
  await page.goto('/wp-admin/plugins.php');
  const row = page.locator('tr[data-plugin="comms-church-links/comms-church-links.php"]');
  await row.getByRole('link', { name: /^Deactivate/ }).click();
  await expect(row).toHaveClass(/\binactive\b/);
  await row.getByRole('link', { name: /^Activate/ }).click();
  await expect(page.locator('#message')).toContainText('activated');
  await expectClean(page);
  await expect(row).toHaveClass(/\bactive\b/);
  await expect(row.getByRole('link', { name: 'Settings' })).toBeVisible();
  await expect(row.getByRole('link', { name: 'Check for updates' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Set up your links page' })).toBeVisible();
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
  // A bulletin page comes in as a plain link; a sermon archive opens its newest sermon.
  await expectFinding(review, 'Bulletin', '/bulletin/', true);
  await expect(review.locator('.chs-findings li', { hasText: '/bulletin/' })).not.toContainText('newest');
  await expectFinding(review, 'Sermons', '/sermons/', true);
  await expect(review.locator('.chs-findings li', { hasText: '/sermons/' })).toContainText('Always opens the newest one.');

  await page.getByRole('button', { name: 'Use selected' }).click();
  await expect(page.locator('.notice-success')).toContainText(/Filled in \d+ settings? from your site/);
  await expectClean(page);
  // The scan runs once; the settings form is what comes up from now on.
  await expect(page.locator('.chs-review')).toHaveCount(0);
  await expect(page.locator('#chs-form')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Set up your links page' })).toHaveCount(0);
});

test('settings save and persist', async ({ page }) => {
  await page.goto(SETTINGS);
  const name = page.locator('input[name="chs_settings[church_name]"]');
  await name.fill('Harbor Church Smoke');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
  await expectClean(page);
  await expect(page.locator('input[name="chs_settings[church_name]"]')).toHaveValue('Harbor Church Smoke');
  // Latest-post links say what they open right now.
  const sermons = page.locator('.chs-app[data-type="watch"]', { has: page.locator('input[value$="/sermons/"]') });
  await expect(sermons.locator('.chs-watch-now')).toContainText('Rest for the Weary');
  // The QR code is drawn for the live address.
  await expect(page.locator('#chs-qr svg path')).toHaveAttribute('d', /^M/);
  await expect(page.getByRole('button', { name: 'Download QR (PNG)' })).toBeVisible();
});

test('/links is a link list that follows the newest sermon', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  const res = await page.goto('/links/');
  expect(res.status()).toBe(200);
  await expectClean(page);
  await expect(page.locator('h1')).toHaveText('Harbor Church Smoke');
  await expect(page.locator('.cl-next [data-svc="time"]')).not.toBeEmpty();
  // No Planning Center key yet, so nothing is featured.
  await expect(page.locator('.cl-featured')).toHaveCount(0);

  const links = page.locator('nav.cl-links');
  await expect(links.locator('a.cl-link', { hasText: 'Bulletin' })).toHaveAttribute('href', /\/bulletin\/$/);
  const sermons = links.locator('a.cl-link', { hasText: 'Sermons' });
  await expect(sermons.locator('.cl-detail')).toHaveText('Rest for the Weary');

  // Phones get no QR code; it's only for carrying the page from a computer to a phone.
  await expect(page.locator('.cl-qr')).toBeHidden();

  // Panels open in place.
  await links.locator('summary', { hasText: 'Contact' }).click();
  await expect(page.locator('#cl-contact')).toContainText('555-0100');

  // The church posts a new sermon; the page follows with no one approving it.
  await page.goto('/wp-admin/?chs_test_sermon=1');
  await expect(page.locator('body')).toContainText('chs sermon posted');
  await page.goto('/links/');
  await expect(sermons.locator('.cl-detail')).toHaveText('When God Feels Far');
  await expect(sermons.locator('.cl-new')).toHaveText('New');
});

test('on a computer, /links offers a QR code to open it on a phone', async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto('/links/');
  await expect(page.locator('.cl-qr')).toBeVisible();
  await expect(page.locator('#clQr svg path')).toHaveAttribute('d', /^M/);
});

test('phone layout is still there as an option', async ({ page }) => {
  await page.goto(SETTINGS);
  await page.locator('input[name="chs_settings[display]"][value="phone"]').check();
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('#setting-error-settings_updated')).toBeVisible();
  await expectClean(page);
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
  // Latest-post links work here too, with a badge for a sermon from this week.
  await expect(page.locator('#home a.app', { hasText: 'Sermons' }).locator('.badge')).toHaveCount(1);
});

test('Planning Center source lists only Church Center events', async ({ page }) => {
  // The blueprint's fake API (chs-test-pco.php) stands in for Planning Center.
  await page.goto(SETTINGS);
  await page.locator('input[name="chs_settings[events_source]"][value="pco"]').check();
  await page.locator('input[name="chs_settings[pco_app_id]"]').fill('wrong-id');
  await page.locator('input[name="chs_settings[pco_secret]"]').fill('wrong-secret');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  // A bad key says so on the settings page instead of failing silently.
  await expect(page.locator('.chs-events-status.is-error')).toContainText('Invalid Application ID or Secret.');
  await expect(page.locator('.chs-pco-status.is-error')).toHaveCount(1);

  await page.locator('input[name="chs_settings[pco_app_id]"]').fill('test-app-id');
  await page.locator('input[name="chs_settings[pco_secret]"]').fill('test-secret');
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('.chs-events-status.is-ok')).toContainText('1 event is shown in Church Center.');
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

test('what is featured in Planning Center leads the page, and follows Planning Center', async ({ page }) => {
  // Key saved by the test above; the fake API features Fall Retreat, plus one awaiting
  // approval and one that isn't featured at all, which must not show.
  await page.goto(SETTINGS);
  await expect(page.locator('.chs-featured-status.is-ok')).toContainText('1 featured event.');
  await page.locator('input[name="chs_settings[display]"][value="list"]').check();
  await page.locator('#chs-form').getByRole('button', { name: 'Save changes' }).click();
  await expect(page.locator('#setting-error-settings_updated')).toBeVisible();

  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/links/');
  await expectClean(page);
  const feat = page.locator('.cl-featured .cl-feat');
  await expect(feat).toHaveCount(1);
  // A featured sign-up links straight to the registration, in Church Center.
  await expect(feat.first()).toContainText('Fall Retreat');
  await expect(feat.first()).toContainText('Sign up by Sunday.');
  await expect(feat.first()).toHaveAttribute('href', 'https://harborchurch.churchcenter.com/registrations/events/201');
  await expect(feat.first()).toHaveAttribute('data-cc', '');
  await expect(page.locator('.cl-featured')).not.toContainText('Trunk or Treat');
  await expect(page.locator('.cl-featured')).not.toContainText('Staff Retreat');
  // It sits above the next service, the first thing people see.
  const featY = (await page.locator('.cl-featured').boundingBox()).y;
  const nowY = (await page.locator('.cl-now').boundingBox()).y;
  expect(featY).toBeLessThan(nowY);

  // Staff feature another event in Planning Center: it appears, soonest first, with no
  // sign-up so it links to its Church Center page. Unfeature it and it's gone.
  await page.goto('/wp-admin/?chs_test_feature=1');
  await expect(page.locator('body')).toContainText('chs feature 1');
  await page.goto('/links/');
  await expect(feat).toHaveCount(2);
  await expect(feat.nth(1)).toContainText('Christmas Eve Services');
  await expect(feat.nth(1)).toHaveAttribute('href', /churchcenter\.com\/calendar\/event\/2045$/);
  await page.goto('/wp-admin/?chs_test_feature=0');
  await expect(page.locator('body')).toContainText('chs feature 0');
  await page.goto('/links/');
  await expect(feat).toHaveCount(1);
});

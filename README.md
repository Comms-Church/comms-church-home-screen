# Church Links

Developer notes. The WordPress.org-style description is in `readme.txt`.

WordPress plugin from comms.church (Strategic Media). It gives a church one link page (link-in-bio, bulletin QR code) that sets itself up from the church's own site and stays current: watched links follow the newest bulletin, sermon or post without anyone editing them. Church Center is built in. It shows as a link list (default) or a phone home screen. The goal is a generic plugin any church can install and set up in minutes. GCC Willow Street (GCCWS) is the first real install.

Renamed from Church Home Screen on 2026-10-26, when the function (scan, link tree, watch, QR) became the product and the phone UI became one display option.

Current version: 0.2.0. It was built and tested in a Claude cloud session on 2026-10-25 against WordPress 6.8+/7.2-beta on SQLite with PHP 8.3.

## Layout

```
comms-church-links.php      Bootstrap, constants (CHS_VERSION etc.), hooks
includes/icons.php                27-icon SVG library, chs_icon_svg(), chs_glyph_color()
includes/class-chs-settings.php   Option schema + defaults, sanitize(), admin page, scan review screen, welcome notice
includes/class-chs-events.php     Events from Planning Center (Church Center events), an iCal feed (own RRULE parser) or The Events Calendar; 1h transient cache
includes/class-chs-frontend.php   Rewrite route (/links by default), standalone render, [church_links] shortcode (iframe; [church_home_screen] still works), build() resolves settings into apps
includes/class-chs-watch.php      Watched links: newest post of an archive (live) or newest link on a page (stored in chs_watch)
includes/class-chs-scanner.php    "Auto-fill from your site": scan() returns findings, apply() saves only checked ones
includes/class-chs-updater.php    GitHub Releases updater (admin only), modeled on the groups plugin's
templates/list.php                Link-list layout (default): full HTML document, not the theme
templates/screen.php              Phone layout: lock screen, app grid, dock
templates/parts/                  Visit, Events and Contact panels, shared by both layouts
assets/list.css, list.js          Link list: next service, panels that open in place, Church Center modal, copy buttons
assets/screen.css, screen.js      Phone: lock/unlock gesture, app panels, Church Center modal, copy buttons
assets/admin.css, admin.js        Settings page: sortable link repeater, media picker, QR code (SVG/PNG download)
assets/vendor/qrcode.js           qrcode-generator 1.4.4 by Kazuhiko Arase (MIT)
uninstall.php, readme.txt
tests/                            Playwright smoke suite + Playground blueprint (port 9405)
.github/workflows/release.yml     Tag-gated build: header Version and CHS_VERSION must equal the tag
```

## Key decisions

- All settings live in one option, `chs_settings`. Everything passes through `CHS_Settings::sanitize()`, including scanner applies (they go through `update_option`).
- The page renders standalone through a rewrite rule with query var `chs_screen`. It doesn't load the theme, so theme CSS can't break it. Changing the path sets `chs_flush_rewrite`.
- App types: `sheet` (in-page panel: visit/events/contact), `cc` (Church Center path), `app` (Church Center app store link), `link`, `watch`. Apps with no URL, and Church Center apps without a Church Center subdomain, are hidden, never shown broken.
- Church Center:
  - Phones get plain churchcenter.com links, which open the Church Center app through iOS Universal Links and Android App Links.
  - Desktop (min-width 721px plus hover) adds `data-open-in-church-center-modal="true"` and loads `https://js.churchcenter.com/modal/v1`. This is optional via the `cc_modal` setting.
  - Store links: App Store id1357742931, Play `com.ministrycentered.churchcenter`.
- Events sources (2026-10-26: the Planning Center one replaced "no API keys"):
  - **Planning Center** (`pco`): only events with `visible_in_church_center` and `approval_status` A, i.e. what the church's Church Center calendar shows. A church's iCal "subscribe" link is its whole calendar (staff meetings, home addresses), and Church Center publishes no feed of its own; its `/~ccapi/` endpoint 401s from a server. Fields were checked against GCC's live account: `Event.visible_in_church_center` is queryable; `EventInstance` carries `starts_at`, `all_day_event`, `location`, `church_center_url`.
  - One request for the visible events, then one per event for its next 10 dates (instances can't be filtered by event). GCC: 6 visible of 4,136 events → 7 requests, under 1 s. Capped at 40 events.
  - Location is "Name - full address" in Planning Center; only the name is shown, like Church Center. A bare street address is dropped.
  - Key: the one entered in settings, else `ccpco_app_id`/`ccpco_secret` from comms-church-pco (when active), else pco-tec's `cctec_*` pair. The secret is never printed back into the form. Saving fetches once so the settings page can say whether the key works (`chs_events_status`).
  - **iCal feed** and **The Events Calendar** (pairs with comms-church-pco-tec) are still there.
- Scanner:
  - Fetches the homepage plus up to 5 info pages with `wp_remote_get` as a logged-out visitor, so page-builder (Bricks) output is read. Falls back to post content + post meta when the site can't fetch itself.
  - Also reads JSON-LD (Yoast/Rank Math `@graph`), tel:/mailto: links, nav menus, pages, sermon CPT archives, and palettes (theme.json, Kadence `kadence_global_palette`, Bricks `bricks_color_palette`). Bricks palettes come in two shapes: older ones store `hex`, while variable-based ones (our design system) store `raw: var(--…)` plus `light`/`dark`. Both are read.
  - Address: page text runs blocks together, so a footer like "Sun 11 AM" + "ADDRESS 212 Peach Bottom Road" (GCCWS) used to match from the 11. The street now starts at the last house number when a time or an all-caps heading comes before it.
  - Church name comes from JSON-LD, then from the homepage's logo/site-title link back home (text or image alt), then `og:site_name`. On Bricks builds the header logo often shows a different name than the WordPress site title.
  - Service-time parsing is the fragile part. It handles "8:30, 10 & 11:30 AM", "9AM + 11AM", "a.m.", and per-time labels ("Sunday School 9:15; Worship 10:30"), and it ignores "Romans 8:28" and "since 1952". A name written after the time ("9:00 AM · Classic gathering") replaces a generic "Worship" but not a specific name written before the time ("Sunday School 9:15"). Page text runs lines together, so a name after a time only counts when the next time follows the same "time · name" pattern; otherwise "11 AM | Bible study 10 AM" would name the wrong service.
  - Main weekend services come pre-checked; everything else starts unchecked.
- Watched links (`watch`), added 2026-10-26. Changes go live on their own (Addison's call: no approval queue). A link that changed in the last 7 days shows "New" (a red badge in the phone layout).
  - A post type archive or the blog page on this site: the newest published post, queried on every page view, so it's never stale. The "Only links containing" words become a search filter.
  - Any other page: fetched (loopback, falling back to stored content) with header/nav/footer stripped. Links with the match words win; then documents (PDF, Office, Google Docs/Drive, Canva, Issuu and the like) over pages; then the latest date written in the link text or URL ("October 26, 2026", "10/26/26", "2026-10-26", upload paths); else the first link. Stored in `chs_watch`, re-checked when that page is saved, when settings are saved, and daily (`chs_watch_daily` cron, scheduled only while something is watched). A failed fetch keeps the last good link.
  - The scanner offers bulletin/newsletter pages and sermon archives as watched links. A page cache (Kinsta) can delay what visitors see by its own TTL.
  - No Asana integration: GCC's comms plan lives in Asana, but what visitors need from it is usually a registration, which is a Church Center link already.
- First visit to Settings → Church Links auto-scans once (`chs_autoscanned`) when `chs_settings` doesn't exist yet. Activation sets `chs_welcome` for the admin notice.
- QR code: drawn in the browser on the settings page from the live URL (so it follows path changes), downloadable as SVG or a 1200px PNG. Error correction M.
- Phone layout design: dawn wallpaper SVG with an optional steeple. Fonts are Bricolage Grotesque (display) and Atkinson Hyperlegible (body); turn Google Fonts off with the `chs_use_google_fonts` filter. Inside the phone frame at desktop sizes, full-bleed on phones (≤520px), where the fake status bar and home bar are hidden.
- WordPress coding standards: tabs, `chs_`/`CHS_` prefixes, escape on output, text domain `comms-church-links`. The `chs_` option names and `CHS_` classes kept their original names through both renames. Because the folder changed, a site with Church Home Screen installed sees Church Links as a separate plugin: delete the old one first (its uninstall clears `chs_settings`), then install this and let it scan.
- Brought into the suite 2026-10-26 as `comms-church-home-screen`, with the updater, `release.yml` and smoke suite; renamed to `comms-church-links` the same day. Repo: `Comms-Church/comms-church-links` (public, so the updater can read releases; the old repo name redirects).

## Tested

- iCal parser: weekly BYDAY, monthly "1SU" with COUNT, EXDATE, RECURRENCE-ID overrides, CANCELLED, all-day, UTC vs TZID, the November DST change.
- Scanner against a seeded sample church site: 16/16 correct suggestions after two fixes.
- Full admin flow: first-visit scan → review → apply → live page. Settings save, adding apps, changing the path (old path 404s, new one 200s), sanitizing junk input.
- Front end at 1280px desktop and 390px iPhone: swipe unlock, panels, Church Center modal attribute, the iOS store link swap.

- Real Bricks build (testchurch.localhost, 2026-10-26): the first scan missed the church name (no JSON-LD; the header logo said otherwise), the service names (written after the times) and the brand color (variable-based Bricks palette). All three are fixed and covered by the smoke suite's blueprint fixtures.

- Planning Center source against GCC's live account on testchurch (key from pco-tec): exactly the six Church Center events, no street addresses, every event linked to its Church Center page. The smoke suite fakes the API in Playground (`chs-test-pco.php`): bad key → error on the settings page; hidden, unapproved and staff events never shown.

Not tested: the live Church Center modal script, Google Fonts, a real Planning Center iCal feed in the parser beyond one fetch, multisite.

## Next steps

1. Try it on GCCWS staging, then cut the first release with the suite's `cut-a-release` skill.
2. Make a comms.church landing page (`page-<slug>.php` in the theme repo plus a card in `page-resources.php`).
3. Ideas not built yet: simple tap analytics per link, more wallpapers, Spanish strings, and a WordPress.org listing once it's generic enough.

## Testing locally

To re-run the first-visit scan: `wp option delete chs_settings chs_autoscanned`.

Smoke suite (green, or no release):

```bash
npm install
npx playwright test --project=smoke
```

It covers activation (deactivate + reactivate through the UI), the first-visit auto-scan review and "Use selected", saving settings, and `/links` rendering and unlocking with a swipe. The blueprint adds a test-only mu-plugin (`?chs_test_reset=1`) that puts the plugin back in its just-activated state, so the suite passes on a reused server and never depends on a retry.

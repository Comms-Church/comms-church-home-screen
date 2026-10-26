# Church Home Screen

Developer notes. The WordPress.org-style description is in `readme.txt`.

WordPress plugin from comms.church (Strategic Media). It gives a church a phone-style link-in-bio page with Church Center built in. Visitors swipe up on a lock screen, then tap "apps" for giving, groups, sign-ups, events, directions and other links. The goal is a generic plugin any church can install and set up in minutes. GCC Willow Street (GCCWS) is the first real install.

Current version: 0.2.0. It was built and tested in a Claude cloud session on 2026-10-25 against WordPress 6.8+/7.2-beta on SQLite with PHP 8.3.

## Layout

```
comms-church-home-screen.php      Bootstrap, constants (CHS_VERSION etc.), hooks
includes/icons.php                27-icon SVG library, chs_icon_svg(), chs_glyph_color()
includes/class-chs-settings.php   Option schema + defaults, sanitize(), admin page, scan review screen, welcome notice
includes/class-chs-events.php     Events from iCal feed (own RRULE parser, 1h transient cache) or The Events Calendar
includes/class-chs-frontend.php   Rewrite route (/links by default), standalone render, [church_home_screen] shortcode (iframe), build() resolves settings into apps
includes/class-chs-scanner.php    "Auto-fill from your site": scan() returns findings, apply() saves only checked ones
includes/class-chs-updater.php    GitHub Releases updater (admin only), modeled on the groups plugin's
templates/screen.php              The standalone page (full HTML document, not the theme)
assets/screen.css, screen.js      Front end: lock/unlock gesture, app panels, Church Center modal, copy buttons
assets/admin.css, admin.js        Settings page: sortable app repeater, media picker
uninstall.php, readme.txt
tests/                            Playwright smoke suite + Playground blueprint (port 9405)
.github/workflows/release.yml     Tag-gated build: header Version and CHS_VERSION must equal the tag
```

## Key decisions

- All settings live in one option, `chs_settings`. Everything passes through `CHS_Settings::sanitize()`, including scanner applies (they go through `update_option`).
- The page renders standalone through a rewrite rule with query var `chs_screen`. It doesn't load the theme, so theme CSS can't break it. Changing the path sets `chs_flush_rewrite`.
- App types: `sheet` (in-page panel: visit/events/contact), `cc` (Church Center path), `app` (Church Center app store link), `link`. Apps with no URL, and Church Center apps without a Church Center subdomain, are hidden, never shown broken.
- Church Center:
  - Phones get plain churchcenter.com links, which open the Church Center app through iOS Universal Links and Android App Links.
  - Desktop (min-width 721px plus hover) adds `data-open-in-church-center-modal="true"` and loads `https://js.churchcenter.com/modal/v1`. This is optional via the `cc_modal` setting.
  - Store links: App Store id1357742931, Play `com.ministrycentered.churchcenter`.
- No Planning Center API or keys. Events come from the public iCal feed, or from TEC (pairs with our comms-church-pco-tec plugin).
- Scanner:
  - Fetches the homepage plus up to 5 info pages with `wp_remote_get` as a logged-out visitor, so page-builder (Bricks) output is read. Falls back to post content + post meta when the site can't fetch itself.
  - Also reads JSON-LD (Yoast/Rank Math `@graph`), tel:/mailto: links, nav menus, pages, sermon CPT archives, and palettes (theme.json, Kadence `kadence_global_palette`, Bricks `bricks_color_palette`).
  - Service-time parsing is the fragile part. It handles "8:30, 10 & 11:30 AM", "9AM + 11AM", "a.m.", and per-time labels ("Sunday School 9:15; Worship 10:30"), and it ignores "Romans 8:28" and "since 1952".
  - Main weekend services come pre-checked; everything else starts unchecked.
- First visit to Settings → Home Screen auto-scans once (`chs_autoscanned`) when `chs_settings` doesn't exist yet. Activation sets `chs_welcome` for the admin notice.
- Front-end design: dawn wallpaper SVG with an optional steeple. Fonts are Bricolage Grotesque (display) and Atkinson Hyperlegible (body); turn Google Fonts off with the `chs_use_google_fonts` filter. Inside the phone frame at desktop sizes, full-bleed on phones (≤520px), where the fake status bar and home bar are hidden.
- WordPress coding standards: tabs, `chs_`/`CHS_` prefixes, escape on output, text domain `comms-church-home-screen`. The admin page slug (`?page=church-home-screen`), the `[church_home_screen]` shortcode and the `chs_` option names kept their original names, so links and saved settings don't move.
- Brought into the suite 2026-10-26: renamed from `church-home-screen`, plus the updater, `release.yml` and smoke suite. Repo: `Comms-Church/comms-church-home-screen` (public, so the updater can read releases).

## Tested

- iCal parser: weekly BYDAY, monthly "1SU" with COUNT, EXDATE, RECURRENCE-ID overrides, CANCELLED, all-day, UTC vs TZID, the November DST change.
- Scanner against a seeded sample church site: 16/16 correct suggestions after two fixes.
- Full admin flow: first-visit scan → review → apply → live page. Settings save, adding apps, changing the path (old path 404s, new one 200s), sanitizing junk input.
- Front end at 1280px desktop and 390px iPhone: swipe unlock, panels, Church Center modal attribute, the iOS store link swap.

Not tested: the live Church Center modal script, Google Fonts, a real Planning Center iCal feed, a real Bricks site, multisite. The cloud sandbox couldn't reach those hosts.

## Next steps

1. Try it on GCCWS staging, then cut the first release with the suite's `cut-a-release` skill.
2. Make a comms.church landing page (`page-<slug>.php` in the theme repo plus a card in `page-resources.php`).
3. Ideas not built yet: a QR code on the settings page, simple tap analytics per app, more wallpapers, Spanish strings, and a WordPress.org listing once it's generic enough.

## Testing locally

To re-run the first-visit scan: `wp option delete chs_settings chs_autoscanned`.

Smoke suite (green, or no release):

```bash
npm install
npx playwright test --project=smoke
```

It covers activation (deactivate + reactivate through the UI), the first-visit auto-scan review and "Use selected", saving settings, and `/links` rendering and unlocking with a swipe. The blueprint adds a test-only mu-plugin (`?chs_test_reset=1`) that puts the plugin back in its just-activated state, so the suite passes on a reused server and never depends on a retry.

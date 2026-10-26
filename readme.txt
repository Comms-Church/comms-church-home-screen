=== Church Home Screen ===
Contributors: commschurch
Tags: church, link in bio, church center, planning center, linktree
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A phone-style link page for your church, with Church Center built in. Use it as your link in bio.

== Description ==

Church Home Screen gives your church a single link that looks and works like a phone. Visitors swipe up to unlock, see when the next service is, and tap "apps" for giving, groups, sign-ups, events, directions and anything else you link to.

* Lock screen with a live clock, the next service, and your next event as notifications.
* Home screen with a service-time widget, an optional Bible verse widget, an app grid and a dock.
* Church Center built in: enter your Church Center address and turn on Giving, Groups, Sign-ups, Calendar, Church Center Home and a "Get the App" button that goes to the right app store.
* On phones, Church Center links open the Church Center app when it is installed. On computers they can open in Church Center's own pop-up.
* Events from a calendar feed (iCal from Planning Center Calendar, Google Calendar and others) or from The Events Calendar plugin.
* Plan a Visit and Contact panels built from your settings.
* Three wallpapers: sunrise over the hills (with an optional church on the hill), a brand-color gradient, or your own photo.
* Visitors can add it to their phone's home screen; your site icon becomes the app icon.
* Auto-fill from your site: reads your homepage, menus, pages, theme colors and installed plugins to suggest service times, contact details, your Church Center address, social links and apps. You review every suggestion before it is used.

The page lives at yoursite.org/links by default. Use the [church_home_screen] shortcode to show it inside a normal page.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings → Home Screen. The first visit scans your site and shows what it found; keep what is right.
3. Fill in your church details and service times, enter your Church Center address, and arrange your apps.
4. Open your home screen and put the link in your social bios.

== Frequently Asked Questions ==

= Where do I find my calendar feed link? =
In Planning Center Calendar, share your published calendar and copy its iCal / subscribe link. Any standard .ics feed works, including Google Calendar's public iCal address.

= Does this need a Planning Center API key? =
No. Church Center links and calendar feeds are public, so nothing needs to be authorized.

= Can I turn off Google Fonts? =
Yes: add_filter( 'chs_use_google_fonts', '__return_false' );

== Changelog ==

= 0.2.0 =
* New: Auto-fill from your site, with a review screen. Runs automatically the first time the settings page opens.
* Visit panel button now reads "Plan your visit".

= 0.1.0 =
* First release.

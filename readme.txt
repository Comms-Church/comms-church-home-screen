=== Church Links ===
Contributors: commschurch
Tags: church, link in bio, church center, planning center, qr code
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Your church's link page, set up from your own site and kept current on its own. Church Center built in, QR code included.

== Description ==

Church Links gives your church one link for social bios, slides and the bulletin QR code. It reads your site to set itself up, and its watched links keep pointing at the newest thing, so nobody has to edit it every week.

* Watched links: point one at your bulletin page and it always opens this week's bulletin; point one at your sermon archive and it always opens the latest sermon. New items show a "New" label for a week.
* A QR code for your links page, ready to download as SVG or PNG.
* Shows your next service and next event at the top, then a big button for each link.
* Or choose the phone layout: a lock screen to swipe up, with an app grid and a dock.
* Church Center built in: enter your Church Center address and turn on Giving, Groups, Sign-ups, Calendar, Church Center Home and a "Get the App" button that goes to the right app store.
* On phones, Church Center links open the Church Center app when it is installed. On computers they can open in Church Center's own pop-up.
* Events straight from Planning Center (only the ones you show in Church Center), from a calendar feed (iCal from Google Calendar and others) or from The Events Calendar plugin.
* Plan a Visit and Contact panels built from your settings.
* Three wallpapers: sunrise over the hills (with an optional church on the hill), a brand-color gradient, or your own photo.
* Visitors can add it to their phone's home screen; your site icon becomes the app icon.
* Auto-fill from your site: reads your homepage, menus, pages, theme colors and installed plugins to suggest service times, contact details, your Church Center address, social links and apps. You review every suggestion before it is used.

The page lives at yoursite.org/links by default. Use the [church_links] shortcode to show it inside a normal page.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings → Church Links. The first visit scans your site and shows what it found; keep what is right.
3. Fill in your church details and service times, enter your Church Center address, and arrange your links.
4. Download the QR code and put the link in your social bios.

== Frequently Asked Questions ==

= How does a watched link decide what is newest? =
On a sermon or news archive it is the newest published post. On any other page it reads the links on the page and picks the one with the latest date in its name (like "Bulletin – October 26"), preferring PDFs and documents over pages. If that picks the wrong link, fill in "Only links containing" with a word every right link has. It re-checks when you save that page, and once a day.

= How do I show events from Planning Center? =
Choose "Planning Center: the events you show in Church Center" under Events. If the Comms.Church PCO plugin is connected, there is nothing else to enter. Otherwise create a Personal Access Token at api.planningcenteronline.com and paste its Application ID and Secret. Only events marked to show in Church Center appear, with the place name but never the street address.

= Can I use my Planning Center Calendar iCal link instead? =
We don't recommend it. That link is usually your whole calendar, including staff meetings and home addresses, and anyone who has it can read it. Use the Planning Center option above. Calendar feeds are fine for public calendars such as Google Calendar's public iCal address.

= Does this need a Planning Center API key? =
Only for the Planning Center events list. Church Center links (Give, Groups, Sign-ups and the rest) are public and need nothing.

= Can I turn off Google Fonts? =
Yes: add_filter( 'chs_use_google_fonts', '__return_false' );

== Changelog ==

= 0.3.0 =
* Renamed from Church Home Screen to Church Links.
* New: link-list layout, now the default. The phone home screen is still there under Look → Layout.
* New: watched links that follow the newest bulletin, sermon or post on their own.
* New: QR code on the settings page.

= 0.2.0 =
* New: Auto-fill from your site, with a review screen. Runs automatically the first time the settings page opens.
* Visit panel button now reads "Plan your visit".

= 0.1.0 =
* First release.

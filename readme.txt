=== Saito Calendar Event ===
Contributors: lucastsl
Tags: advent calendar, calendar, countdown, woocommerce, coupon
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Date-unlocked door calendars (Advent calendar, event countdown): each door reveals a text, an image, a product and an optional WooCommerce coupon.

== Description ==

Saito Calendar Event builds **calendars made of doors that unlock on a given date** — an Advent calendar, a countdown before a launch, a "12 days of" campaign… Each door reveals its own reward on its day: a short title, a description, an image, a linked product and, with WooCommerce, a coupon code the visitor can copy or add to their cart in one click.

**Fair by design**

* A door can only be opened **on its exact unlock day**: before, it is locked (with a countdown); after, it is closed for good. The date check is done **server-side**: the reward and the coupon code are never sent to the browser before the date.
* Coupons are re-checked when added to the cart, and requests are rate-limited.

**For visitors**

* Opening animation (a door that swings open), confetti in the colors of the theme, reveal modal with a "Copy" button and an "Add the discount to my cart" button (WooCommerce).
* Opened doors show the photo of their reward; a progress bar counts the doors opened.
* Keyboard accessible, respects "reduced motion" settings.

**Seven designs**

* **Christmas**, **Neutral** and **Editorial** (flat colors, serif type, bento grid).
* **Sparkle** (coral red and gold, fireworks, golden stars) and three **Floral** designs (blue and silver, white and gold, or both mixed): doors of varied sizes in a mosaic, with the 1st and 24th doors as large doors, an optional header showing the calendar title and a subtitle.
* Colors, corner radius and borders can be adjusted, with a live preview.

**For editors**

* A grid of doors in the admin: click a door to set its date, coupon, product (searched by name, with thumbnail, price and stock), texts and image. Drag a door onto another to swap their content.
* Coupon helper: suggestions from your published WooCommerce coupons (discount, status, expiry), and a warning if a code does not exist, is used up, has expired or expires before the door's date.
* Scheduling: show the calendar only between two dates, with an optional "coming soon" and "it's over" message.
* All visitor texts can be customized; calendars can be exported/imported as JSON and duplicated from one year to the next.
* Insert a calendar with the **"Calendrier à cases" block** or the `[saito_calendar_event id="123"]` shortcode.

**Requirements**

WooCommerce is optional: without it, doors reveal their text and image; coupons and linked products need WooCommerce.

The admin interface and default visitor texts are in French.

== Installation ==

1. Upload the `saito-calendar-event` folder to `/wp-content/plugins/`, or install the plugin from the **Plugins → Add New** screen.
2. Activate the plugin.
3. Go to **Calendriers → Ajouter un calendrier**, set up the doors (date, reward, optional coupon), choose a design and publish.
4. Insert the calendar in a page with the "Calendrier à cases" block, or with the shortcode shown in the "Utilisation" tab: `[saito_calendar_event id="123"]`.

== Frequently Asked Questions ==

= Can a visitor open a door before its date? =

No. The date is checked on the server, in the site's timezone. The content of a door (texts, image, coupon) is only sent once the door is opened on its day.

= Does it create the WooCommerce coupons? =

No. Create your coupons in WooCommerce (Marketing → Coupons) as usual, then type the code in the door. The plugin applies it to the cart when the visitor clicks the button, and warns you in the admin if the code looks wrong.

= Can I use it without WooCommerce? =

Yes. Doors then reveal their title, description and image only.

= Does it load anything from external services? =

No. All scripts, styles and fonts are bundled in the plugin; nothing is loaded from a CDN or a third-party service, and no data is sent outside your site.

= What happens to my calendars if I delete the plugin? =

Deleting the plugin from the Plugins screen removes the calendars it created and their settings. Deactivating it keeps everything.

== Screenshots ==

1. The Sparkle design: header with the calendar title, mosaic of doors (the 24th in the middle), today's door and opened doors showing their reward.
2. The Floral duo design, mixing blue-and-silver and white-and-gold doors.
3. The Floral white and gold design on a phone.

== Changelog ==

= 1.0.0 =
* First public release.

== Upgrade Notice ==

= 1.0.0 =
First public release.

== Third-party libraries and fonts ==

This plugin bundles the following open-source software, unmodified:

* canvas-confetti 1.9.4 (MIT License) — https://github.com/catdad/canvas-confetti
* vanilla-tilt.js 1.8.1 (MIT License) — https://github.com/micku7zu/vanilla-tilt.js
* SortableJS 1.15.6 (MIT License) — https://github.com/SortableJS/Sortable
* Bodoni Moda (SIL Open Font License 1.1) — https://github.com/indestructible-type/Bodoni
* Great Vibes (SIL Open Font License 1.1) — https://github.com/googlefonts/great-vibes

The files in `assets/js/vendor/` are the official, unmodified distribution builds of these projects (canvas-confetti in its readable build; SortableJS and vanilla-tilt.js as published, minified). Their full source code is available at the addresses above. The font license texts are included in `assets/fonts/`.

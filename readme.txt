=== EventCore – Advanced Events & Booking Manager ===
Contributors: abdulrahmansarsar
Tags: events, calendar, rsvp, event management, qr code tickets, booking
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The ultimate event management system with an interactive Calendar, Sub-events (Sessions), RSVP & Waitlist, local QR Code Check-in, and REST API.

== Description ==

**EventCore** is a comprehensive, highly optimized WordPress plugin designed for organizers who need full control over their events, conferences, and workshops.

Whether you are hosting a single webinar or a multi-day festival with dozens of sessions, this plugin provides a seamless experience for both admins and attendees.

### 🌟 Key Features:
*   **Hierarchical Events:** Create Main Events and link multiple Sub-Events (Sessions/Workshops) to them.
*   **Advanced RSVP & Waitlist:** Limit event capacity. Once full, users are automatically added to a waitlist and auto-promoted when seats free up.
*   **Local QR Code Ticketing:** QR codes are generated **on your own server** (bundled library) and embedded directly in confirmation emails — no third-party image service, no privacy leaks.
*   **Smart Check-in System:** Scan QR codes at the door or manually mark attendees as "Checked-in" from the admin dashboard.
*   **Paid Tickets (WooCommerce):** Optional integration to sell ticket tiers (VIP, Standard, etc.) through WooCommerce.
*   **Interactive AJAX Calendar:** A modern, fully responsive calendar with smart filtering and zero page reloads.
*   **Recurring Events:** Daily / weekly / monthly / yearly repetition with a safe 5-year generation cap.
*   **Anti-Spam Protection:** Built-in honeypot, submission-age check, per-IP rate limiting, and optional disposable-email blocking — no external CAPTCHA required.
*   **Reliable Email Delivery:** A database-backed email queue with retry & backoff so large reminder batches never block or time out.
*   **Event SEO Schema:** Automatic JSON-LD (Event + Offers + Place/VirtualLocation) for Google rich results.
*   **CSV Import & Export:** Bulk import events via CSV, and export your attendee lists with a single click.
*   **Automated Emails:** Customizable confirmation and 24-hour reminder emails with dynamic tags.
*   **REST API Ready:** Headless-ready with custom endpoints for apps (Flutter, React Native).
*   **Translation Ready:** Fully localized (text domain `core-events-pro`) and RTL-ready out of the box.

== Installation ==

1. Upload the `core-events-pro` folder to the `/wp-content/plugins/` directory, or upload the `.zip` file via the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Follow the **Setup Wizard** that opens automatically after activation.
4. Use the provided shortcodes (e.g., `[event_calendar]`) to display events on your pages.

== Frequently Asked Questions ==

= How do I scan the QR codes? =
Any site administrator or editor can simply scan the QR code using their smartphone camera. It opens a secure link that automatically checks the attendee in. Only logged-in users with editing capabilities can complete a check-in.

= Are QR codes generated using an external service? =
No. QR codes are generated locally on your server using a bundled library and inlined into emails as image data. Nothing is sent to a third party.

= Can I customize the email templates? =
Yes! Go to the plugin's "Settings & Help" page. You can customize the subject and body of both the confirmation and reminder emails using dynamic tags like `{name}` and `{event_name}`.

= Does it support RTL languages? =
Absolutely. The plugin's frontend and backend are carefully coded to support Right-To-Left (RTL) languages like Arabic out of the box.

= Does the plugin require WooCommerce? =
No. WooCommerce is only needed if you want to sell **paid** tickets. Free RSVP, waitlist, QR check-in, calendar, and the REST API all work without it.

== Third-Party / External Services ==

This plugin is self-contained and makes **no external requests by default**. The following are disclosed for full transparency:

* **phpqrcode** (bundled library, LGPL 3.0) — generates QR codes locally on your server. Source: https://github.com/t0k4rt/phpqrcode. No data leaves your site.
* **License verification (optional, off by default):** The license screen can validate a CodeCanyon purchase code. Remote verification only happens if the site owner explicitly configures a license server URL (`CEP_LICENSE_SERVER`) or an Envato Personal Token (`CEP_ENVATO_TOKEN`). With no configuration, the plugin only checks the purchase-code format locally and never contacts any server. When enabled, only the purchase code and site domain are transmitted to the configured endpoint.

== Screenshots ==

1. The modern AJAX calendar in action.
2. The sleek Single Event page with RSVP form.
3. The Admin Dashboard showing total registrations and check-ins.
4. The Attendees WP_List_Table for easy management.
5. The License activation screen.

== Changelog ==

= 1.0.0 =
* Initial CodeCanyon release.
* Hierarchical Main Events + Sub-Events (sessions).
* Free RSVP with smart waitlist and automatic promotion.
* Paid tickets via optional WooCommerce integration.
* Local QR-code ticket generation (no external service) and QR / manual check-in.
* Interactive AJAX calendar with category filtering and recurring events.
* Anti-spam layer for the public RSVP form (honeypot, age check, per-IP rate limit, optional disposable-email blocking).
* Database-backed asynchronous email queue with retry and backoff.
* Automatic Event JSON-LD schema for SEO rich results.
* CSV import (events) and CSV export (attendees).
* Customizable confirmation and 24-hour reminder emails.
* REST API (`events/v1`) for headless / mobile apps.
* Setup Wizard and Envato license activation screen.
* Full i18n support and RTL-ready styling.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

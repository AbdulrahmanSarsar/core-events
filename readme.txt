=== Core Events Pro ===
Contributors: abdulrahmansarsar
Tags: events, calendar, rsvp, event management, qr code tickets, booking
Requires at least: 5.8
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

The ultimate event management system with an interactive Calendar, Sub-events (Sessions), RSVP Waitlist, QR Code Check-in, and REST API.

== Description ==

**Core Events Pro** is a comprehensive, highly optimized WordPress plugin designed for organizers who need full control over their events, conferences, and workshops. 

Whether you are hosting a single webinar or a multi-day festival with dozens of sessions, this plugin provides a seamless experience for both admins and attendees.

### 🌟 Key Features:
*   **Hierarchical Events:** Create Main Events and link multiple Sub-Events (Sessions/Workshops) to them.
*   **Advanced RSVP & Waitlist:** Limit event capacity. Once full, users are automatically added to a waitlist.
*   **QR Code Ticketing:** Automatically generate and email secure QR code tickets to confirmed attendees.
*   **Smart Check-in System:** Scan QR codes at the door or manually mark attendees as "Checked-in" from the admin dashboard.
*   **Interactive AJAX Calendar:** A modern, fully responsive calendar with smart filtering and zero page reloads.
*   **CSV Import & Export:** Bulk import events via CSV, and export your attendee lists with a single click.
*   **Automated Emails:** Send customizable confirmation and 24-hour reminder emails to attendees.
*   **REST API Ready:** Headless-ready with custom endpoints for apps (Flutter, React Native).
*   **Translation Ready:** Fully localized and ready for any language.

== Installation ==

1. Upload the `core-events-pro` folder to the `/wp-content/plugins/` directory, or upload the `.zip` file via the WordPress Plugins menu.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Navigate to **Events Pro** in the admin menu to configure your settings and create your first event.
4. Use the provided shortcodes (e.g., `[event_calendar]`) to display events on your pages.

== Frequently Asked Questions ==

= How do I scan the QR codes? =
Any site administrator or editor can simply scan the QR code using their smartphone camera. It will open a secure link to automatically check the attendee in.

= Can I customize the email templates? =
Yes! Go to the plugin's "Settings & Help" page. You can customize the subject and body of both the confirmation and reminder emails using dynamic tags like `{name}` and `{event_name}`.

= Does it support RTL languages? =
Absolutely. The plugin's frontend and backend are carefully coded to support Right-To-Left (RTL) languages like Arabic out of the box.

== Screenshots ==

1. The modern AJAX calendar in action.
2. The sleek Single Event page with RSVP form.
3. The Admin Dashboard showing total registrations and check-ins.
4. The Attendees WP_List_Table for easy management.

== Changelog ==

= 1.0.0 =
* Major Release: Rebuilt from the ground up for maximum performance and security.
* Added: Waitlist system for fully booked events.
* Added: QR Code ticket generation and scanning system.
* Added: Advanced automated reminder emails (Cron jobs).
* Added: Full i18n translation support.
* Improved: Strict security escaping, sanitization, and Nonce validation across all endpoints.

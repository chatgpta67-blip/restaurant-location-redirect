=== Restaurant Location Order Redirect ===
Contributors: digipro
Tags: restaurant, geolocation, redirect, multi-location, elementor
Requires at least: 5.8
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Detects a visitor's likely restaurant location and dynamically points "Order Now" buttons to the correct location-specific ordering URL, with a manual selector fallback.

== Description ==

For a restaurant brand with multiple locations, each with its own external ordering URL, this plugin:

* Lets an administrator manage locations (name, city, state, country, order URL, active status) from **Settings → Location Order Redirect**.
* Detects a visitor's approximate location via server-side IP geolocation (provider configurable; API keys never reach the browser).
* Matches the detected location against configured locations using a city → state → country → proximity hierarchy, with a configurable confidence threshold.
* Dynamically updates every "Order Now" button (matched via a configurable CSS selector) to the correct location's order URL — no per-button editing required, and it works with Elementor.
* Falls back to a "Select Your Location" popup whenever detection is missing, ambiguous, or fails, so the visitor is never stuck.
* Remembers the visitor's manual choice (cookie/localStorage, configurable duration) and always treats a manual choice as authoritative over IP detection.
* Works safely behind full-page caching and CDNs: no visitor-specific HTML is ever rendered server-side.
* Includes optional, privacy-conscious, disabled-by-default analytics with an internal dashboard and an opt-in GA4 integration hook.

See `docs/README.md` in the plugin folder for full installation, configuration, Elementor, geolocation-provider, analytics, testing, security, and privacy documentation.

== Installation ==

1. Upload the `restaurant-location-redirect` folder to `/wp-content/plugins/`, or upload the ZIP via **Plugins → Add New → Upload Plugin**.
2. Activate the plugin through the **Plugins** menu.
3. Go to **Settings → Location Order Redirect → Locations** and add your restaurant locations.
4. Go to the **General** tab and set the CSS selector that matches your Order Now buttons.
5. (Optional) Go to the **Geolocation** tab to choose a provider and, if required, add an API key.

== Frequently Asked Questions ==

= Does this work with Elementor? =

Yes. Add the configured CSS class (default `order-now` or `order-now-button`) to any Elementor button's Advanced → CSS Classes field. The plugin updates the `href` of matching elements after the page loads.

= Will this break my page cache? =

No. All HTML is static and identical for every visitor. Location detection and button updates happen entirely in the browser via JavaScript and a REST endpoint that is sent with no-cache headers.

= What happens if geolocation fails or is inconclusive? =

The visitor sees the "Select Your Location" popup and can choose manually. The rest of the site is unaffected.

== Changelog ==

= 1.0.6 =
* Fixed: a critical bug in 1.0.5's Order Now button state-code badge could freeze the page for every visitor. The badge was rewritten on every reapplication (including reapplications triggered by the plugin's own "watch for new buttons" observer), and each rewrite counted as page activity that triggered the same observer again, looping indefinitely and pegging the browser tab. Reapplying an unchanged badge is now a true no-op. Sites running 1.0.5 should update immediately.

= 1.0.5 =
* Added: matched Order Now buttons now show a short state-code badge next to the label (e.g. "Order Now (AZ)"), derived automatically from the location's State field. Recognizes standard US state/territory names and already-2-letter codes; a state value that isn't recognized (e.g. a non-US region) simply shows no badge.

= 1.0.4 =
* Fixed: the admin debug block (detected IP/country/state/city) never appeared from /detect, even with Debug Mode enabled and while logged in as an administrator. The request was missing the X-WP-Nonce header, so WordPress's REST cookie authentication treated it as an anonymous visitor regardless of the active login session. Fixed in both the Debug & Help "Run Detection Test" button and the frontend debug panel.

= 1.0.3 =
* Fixed: the "Run Detection Test" and "Simulate a Detected Location" buttons on the Debug & Help tab silently did nothing. Their inline script ran before wp_localize_script's footer-printed config existed, so it exited before attaching any click handlers. Deferred to DOMContentLoaded. (This bug affected Run Detection Test since 1.0.0.)

= 1.0.2 =
* Added an admin-only "Simulate a Detected Location" tool (Debug & Help tab) that runs the city/state/country/proximity matching logic against typed-in values, with no real geolocation API call — for verifying matches from anywhere without a VPN.

= 1.0.1 =
* Removed unused translation files from the bundled update-checker library to reduce the plugin's file count (some hosts fail to fully extract/move very large numbers of small files during install).

= 1.0.0 =
* Initial release.

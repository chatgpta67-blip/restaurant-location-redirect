# Restaurant Location Order Redirect

A WordPress plugin that detects a restaurant visitor's likely location (saved preference → IP geolocation → manual selection) and dynamically points every "Order Now" button to that location's external ordering URL — without ever caching one visitor's location into another visitor's page.

This document covers everything in the project deliverables list: installation, configuration, Elementor integration, geolocation provider setup, analytics, testing, security, privacy, troubleshooting, and example configuration.

---

## 1. Installation

1. Zip the `restaurant-location-redirect` folder (excluding `node_modules`, `tests`, `docs`, `package.json`, `phpunit.xml.dist` — see `.distignore`), or use the folder directly.
2. In WordPress: **Plugins → Add New → Upload Plugin**, choose the ZIP, click **Install Now**, then **Activate**. (Or copy the folder into `wp-content/plugins/` via FTP/SSH and activate from **Plugins**.)
3. Activation automatically creates two database tables (`wp_rlr_locations`, `wp_rlr_analytics_events`) and seeds default settings. No manual database work is required.
4. No WordPress core or Elementor core files are modified. Deactivating the plugin leaves your data intact; data is only removed if you explicitly opt in (see §9 Privacy).

**Requirements:** WordPress 5.8+, PHP 7.4+. No other plugin is required, though it coexists with any page-builder (Elementor, etc.) and any caching plugin.

---

## 2. Configuration

All settings live under **Settings → Location Order Redirect**, organized into tabs:

### General tab
| Setting | Purpose |
|---|---|
| Enable Plugin | Master on/off switch. |
| Order Button CSS Selector | Comma-separated CSS selector(s) matched against your "Order Now" links, e.g. `.order-now, .order-now-button`. |
| Change Location Trigger Selector | Selector for elements that re-open the popup, e.g. `.rlr-change-location`. The `[rlr_change_location]` shortcode already carries this class. |
| Storage Duration | How many days a selected/detected location is remembered (default 30). |
| Storage Method | Cookie, localStorage, or both (recommended). |
| Automatic Geolocation | Whether to attempt IP-based detection at all. |
| Location Popup | Whether to show the manual selector when detection is inconclusive. |
| Debug Mode | Shows a floating debug panel with detection details, visible only to logged-in administrators. |
| Uninstall Behavior | Opt-in checkbox to permanently delete all plugin data when the plugin is deleted. |

### Locations tab
Add, edit, deactivate, or delete locations. Each location has: Name, City, State/Region, Country (+ optional 2-letter country code), Order URL, optional Latitude/Longitude, Active/Inactive status, and a sort order. URLs are validated (must be `http(s)://`) before saving; the URL is stored as an ordinary external link — the ordering platform can be on a completely different domain.

### Geolocation tab
Choose a provider, add its API key if required, and tune caching/rate-limiting/confidence behavior. See §5.

### Analytics tab
Dashboard + settings. See §6.

### Debug & Help tab
A one-click "Run Detection Test" tool (admin-only), plus in-product privacy documentation and a troubleshooting quick-reference.

---

## 3. How location detection works

```
Saved location exists (cookie/localStorage)?
  ├── YES → use it immediately. No popup. No geolocation call.
  └── NO  → is Automatic Geolocation enabled?
             ├── NO  → show popup (if enabled) / leave buttons as-is.
             └── YES → call /wp-json/rlr/v1/detect (server-side IP lookup)
                        ├── confident match (≥ configured threshold) → auto-select,
                        │   store it, update buttons, done.
                        └── low/no confidence, or the API call failed →
                            show the "Select Your Location" popup.
```

A manual selection (via the popup or the location switcher) is always stored and always **overrides** IP detection on the next page view — the plugin never re-runs geolocation once a location (auto or manual) is saved. This matches the required principle: *IP detection is a convenience; the visitor's explicit choice is the source of truth.*

**Matching hierarchy** (`RLR_Matcher`, see `includes/class-rlr-matcher.php`): city → state/region → country → optional lat/lng proximity. Each tier requires the country to match first, to avoid false positives across countries with similarly-named cities/states. A tier that has more than one equally-good candidate is treated as ambiguous and falls through to the next tier (or to proximity, or to the popup) rather than guessing.

**Confidence levels:** `high` (city-level or close proximity match), `medium` (state-level or medium-distance proximity match), `low` (country-level only, or an ambiguous tie), `none` (no usable match). The **Confidence Threshold** setting decides how high a result must be before the plugin auto-selects instead of showing the popup.

---

## 4. Elementor integration

The plugin does not touch Elementor's code or database — it works purely via CSS selector on the rendered HTML:

1. Select your Elementor button widget.
2. Open **Advanced → CSS Classes** (or Attributes) and add the class configured in **Order Button CSS Selector** (default `order-now` or `order-now-button` — add the class *without* the leading dot).
3. Repeat for every Order Now button/section, including inside Elementor popups, headers, and footers built with Elementor Pro's theme builder.
4. The plugin's JS uses a `MutationObserver`, so buttons that Elementor injects after page load (lazy-loaded sections, AJAX popups) are picked up automatically once a location is applied — no extra configuration needed.

Multiple buttons, on the same page or across a multi-section layout, are all updated in one pass.

---

## 5. Geolocation provider setup

Providers implement `RLR_Geolocation_Provider` (`includes/interface-rlr-geolocation-provider.php`) and are looked up by the `rlr_geolocation_provider_classes` filter, so adding a new one never requires editing plugin core files.

### Included providers

- **ip-api.com** (`includes/providers/class-rlr-provider-ipapi.php`) — free tier, no API key needed for the HTTP endpoint. Optional key upgrades it to the HTTPS `pro.ip-api.com` endpoint (recommended for production, since the free HTTP endpoint is rate-limited and unencrypted in transit).
- **ipinfo.io** (`includes/providers/class-rlr-provider-ipinfo.php`) — requires an API token (create one at ipinfo.io), used as a Bearer token over HTTPS.

Add the key on the **Geolocation** tab. Keys are stored in the `rlr_settings` option and are only ever used inside server-side `wp_remote_get()` calls (`includes/class-rlr-geolocation.php`) — they are never localized to JavaScript or included in any REST response.

### Adding a third provider

```php
add_filter( 'rlr_geolocation_provider_classes', function ( $classes ) {
    $classes[] = 'My_Custom_Provider'; // implements RLR_Geolocation_Provider
    return $classes;
} );
```

### Caching & rate limiting

Every successful lookup is cached (WordPress transient, keyed by a salted hash of the IP — the raw IP is never used as a cache key) for the configured **Result Cache Duration**. A separate **Rate Limit** setting caps lookups per IP per hour to protect paid API quotas; requests beyond the limit fall back to the manual popup rather than erroring.

---

## 6. Analytics configuration and reporting

Analytics is **disabled by default**. Three modes (General → Analytics tab):

- **Disabled** — nothing is collected, no requests are sent, `rlr_location_event` still fires for third-party integrations but no plugin-side storage happens.
- **Internal aggregated analytics** — events are written to `wp_rlr_analytics_events` and summarized on the dashboard.
- **Internal + external (GA4)** — same as above, plus: if `window.gtag` is already present on the page (i.e. you already run GA4 via your theme/Tag Manager), the plugin also calls `gtag('event', 'rlr_<event>', {...})` with only a location id and match method. The plugin never loads gtag.js itself and never sends IP addresses or coordinates to GA4.

**Events tracked:** `auto_match`, `manual_selection`, `selector_opened`, `location_changed`, `geolocation_failure`, `no_confident_match`, `order_click`. Each stored row contains only: event type, location id, match method, confidence, a random (non-personal) session id, device category (desktop/mobile/tablet), referrer category (direct/internal/search/social/referral), and a timestamp. No names, emails, IPs, or coordinates are ever stored.

**Consent:** if "Require Consent" is on, no tracking request is sent until either (a) a configured consent cookie/value is detected, or (b) you define `window.rlrConsentCheck = function () { return true/false; };` in your theme to integrate with any CMP (CookieYes, OneTrust, etc.). With consent required and neither mechanism present, the default is **closed** (no tracking) — a deliberately privacy-safe default.

**Retention:** a daily WP-Cron job (`rlr_cleanup_analytics`) deletes events older than the configured retention window (default 90 days).

**Dashboard:** **Settings → Location Order Redirect → Analytics** shows total order clicks, clicks/matches/selections per location, location changes, geolocation failures, no-match events, an approximate selection→order-click conversion rate, and a date-filterable daily breakdown.

**Integration hook** for your own analytics stack:

```php
add_action( 'rlr_location_event', function ( $event ) {
    // $event = ['event_type' => 'order_click', 'location_id' => 3, ...]
} );
```

This fires whenever an event is *recorded* (i.e., only when analytics storage is enabled) — see `RLR_Analytics::record()`.

---

## 7. Testing

### Manual test plan (maps to the 12 required test scenarios)

1. **Arizona** — from a US/Arizona IP (or VPN), load the site with no cookies. Expect the Order Now buttons to point at the Arizona order URL after page load, and clicking one to open the Arizona ordering site.
2. **Manchester** — same, from a UK/Manchester IP. Expect the Manchester URL.
3. **Manual overrides IP** — with an Arizona-detected IP, open the popup (Change Location) and pick Manchester. Expect Manchester URLs immediately, and confirm via **Debug & Help → Run Detection Test** that the server still reports Arizona as the IP-detected location while the *applied* location is Manchester (client-side override).
4. **Persistence** — select Manchester, reload the page (no popup, still Manchester). Close and reopen the browser — still Manchester, until the configured storage duration elapses (reduce it to 1 day in settings to test expiry quickly).
5. **Change Location, no refresh** — click "Change Location", pick a different location, confirm buttons update without a page reload (watch the Network tab: no full navigation, just the button `href` attributes changing).
6. **Unknown location** — use a VPN exit node in a country with no configured location. Expect the popup.
7. **Geolocation API failure** — temporarily set an invalid API key (for a key-required provider) or block outbound requests; expect the popup, and confirm no PHP errors/fatals in the debug log.
8. **Multiple buttons** — add 5 elements matching the button selector to one page; confirm all 5 get the same URL. Covered by `tests/js/rlr-public.test.js`.
9. **Elementor** — add an Elementor page with several button widgets carrying the configured class; confirm all update. See §4.
10. **Cache** — enable a page cache/CDN, view the site from two different simulated locations (e.g. two VPN exit points, or two browsers with different `X-Forwarded-For`-spoofing via devtools if your host honors it); confirm each visitor gets their own location, not the other's. The plugin renders no visitor-specific HTML, and the `/detect` REST response is sent with `Cache-Control: no-store, no-cache, must-revalidate, private`.
11. **Mobile** — repeat the above on Android/iOS, Wi-Fi and cellular; the flow is identical since it's plain JS + REST.
12. **VPN** — repeat detection through a VPN across several countries, including one with no configured location, and document that results are approximate (see the in-admin Debug & Help documentation and §9 below).

**Testing from a location with no configured restaurant (e.g. verifying US/UK locations while physically elsewhere):** two options, no VPN required for the first one:
- **Debug & Help → Simulate a Detected Location** — type in (or use the quick-fill buttons for) a city/state/country and it runs the exact same matching hierarchy and confidence-threshold logic used in production, with zero external API calls. This is the fastest way to verify "does Phoenix/Arizona/US correctly resolve to the Arizona location at High confidence" from anywhere in the world. It only exercises the *matching* logic, not the real geolocation provider call itself.
- To test the **full pipeline** including the actual IP geolocation API call, use a VPN exit point in the target region and check **Debug & Help → Run Detection Test** (or just load the site) — this is the only way to verify the real provider (ip-api.com/ipinfo.io) returns usable data for that region.
- Note that from a genuinely unconfigured region (no matching location), the correct and expected behavior is the "Select Your Location" popup appearing — that itself is a pass for Test 6, not a bug.

### Automated tests

**PHP (PHPUnit, using the standard WP core test suite):**

```bash
composer require --dev wp-phpunit/wp-phpunit yoast/phpunit-polyfills
WP_TESTS_DIR="$(pwd)/vendor/wp-phpunit/wp-phpunit" vendor/bin/phpunit -c phpunit.xml.dist
```

Or, using `wp-env` (Docker-based): run `wp-env start`, then `wp-env run tests-cli --env-cwd=wp-content/plugins/restaurant-location-redirect phpunit -c phpunit.xml.dist`.

Covers: `tests/php/test-matcher.php` (the full matching hierarchy — city/state/country/proximity, ambiguity, missing data, confidence thresholds), `test-location-manager.php` (CRUD + validation), `test-settings.php` (sanitization), `test-analytics.php` (recording, disabled no-op, unknown event rejection, no-raw-IP-column assertion, retention cleanup, dashboard aggregation), `test-rest.php` (public endpoints never leak API keys, `/detect` sends no-cache headers and hides `debug` from non-admins, `/track` requires a valid nonce and no-ops when analytics is disabled).

**JavaScript (Jest + jsdom):**

```bash
npm install
npm test
```

`tests/js/rlr-public.test.js` covers the pure helpers (expiry math, slug lookup, consent gating, referrer categorization) and integration behavior of `RlrController` (multi-button updates, manual selection vs. change-location tracking, saved-location persistence/expiry, order-click tracking gated on an actually-applied location, consent gating, and disabled-analytics no-ops). All 21 tests pass against the shipped `public/js/rlr-public.js`.

---

## 8. Security considerations

- **No API keys in the browser.** Provider keys are stored server-side and used only inside `wp_remote_get()` calls; REST responses are covered by a test (`test_locations_endpoint_never_exposes_api_keys`) asserting they never appear in any public response body.
- **Nonces + capability checks.** All admin mutations (save/delete/toggle location, save settings) run through `check_admin_referer()`/the Settings API plus `current_user_can( 'manage_options' )`. The public `/track` REST endpoint requires a valid `wp_rest` nonce; `/detect` and `/locations` are read-only and side-effect-free (aside from cache writes and rate-limited external calls), so they don't mutate state and don't require a nonce.
- **Input sanitization / output escaping.** All admin form input is sanitized in `RLR_Settings::sanitize()` and `RLR_Location_Manager::sanitize_input()`; all admin-rendered values use `esc_html()`/`esc_attr()`/`esc_url()`. All `$wpdb` queries are parameterized via `$wpdb->prepare()`.
- **URL validation.** Order URLs must pass `esc_url_raw()` + `FILTER_VALIDATE_URL` and start with `http://`/`https://` before being saved (`RLR_Helpers::validate_order_url()`), preventing `javascript:`/data-URI injection through the admin form.
- **Rate limiting** on geolocation lookups protects the configured provider's API key/quota from abuse.
- **No fatal-error surface.** Every geolocation call, matcher call, and analytics call is defensively coded (falls back to `WP_Error`/`false`/popup) so a third-party API outage or malformed response cannot crash the site; `RLR_Analytics::record()` is wrapped in try/catch specifically so a storage failure can never break the redirect flow.

---

## 9. Privacy considerations

- **IP addresses are never persisted.** They are read from the request only to perform a geolocation lookup, then discarded. A one-way salted hash of the IP is used briefly (as a WordPress transient key) purely for result caching and rate limiting, and expires automatically.
- **What's stored client-side:** a location slug/id in a first-party cookie and/or `localStorage` entry — nothing else. No names, emails, or precise coordinates.
- **What's stored server-side (only if Analytics is enabled):** aggregated event rows containing an event type, an optional location id, match method, confidence, device category, referrer category, a randomly generated session id, and a timestamp. See §6 for the full list and the retention/cleanup mechanism.
- **Debug information** (detected IP — masked as `203.0.113.xxx` — country/state/city, matched location, confidence) is only ever returned by the REST API, and only, when the requester is logged in as an administrator **and** Debug Mode is enabled; it is never rendered into public page HTML.
- **VPN / IP geolocation accuracy disclaimer:** IP-based geolocation is approximate — VPNs, corporate proxies, mobile carrier NAT, and ISP-level IP allocation can all produce an incorrect country/region/city. This is exactly why the plugin never treats an IP-based result as final without a configurable confidence threshold, and always offers a manual override. Document this for your own site's privacy policy if required in your jurisdiction.
- Uninstalling the plugin **keeps all data by default**; a location-manager administrator must explicitly check "Remove all data on uninstall" (General tab) for `uninstall.php` to drop the plugin's tables/options.

---

## 10. Troubleshooting guide

See also the in-admin **Debug & Help** tab, which mirrors this section and adds a one-click detection test.

| Symptom | Likely cause / fix |
|---|---|
| Buttons don't update | Check the CSS selector actually matches your markup (inspect element); confirm the plugin is enabled; check the browser console for JS errors from *other* plugins/theme scripts blocking execution. |
| Popup never appears | Confirm "Location Popup" is enabled and at least one location is Active. |
| Popup appears every visit | Storage may be blocked (private browsing, cookie consent tooling stripping first-party cookies) or `storage_duration_days` may be set very low. |
| Wrong location auto-selected | Lower the confidence threshold's *reach* by raising it (e.g. from Low to Medium/High) so weaker matches fall back to the popup instead of auto-selecting; verify the location's City/State/Country text matches how your geolocation provider spells them (add the ISO country code to reduce ambiguity). |
| One visitor sees another's location | Should not happen — no visitor-specific HTML is rendered server-side. If it does, check that your CDN/cache is not caching the `/wp-json/rlr/v1/detect` response itself (it's sent `no-store`) and that the page's cached HTML still contains the plugin's `<script>` tag (i.e., you haven't cached a page snapshot that predates activating the plugin). |
| REST calls return 401/403 | Aggressive caching can freeze a stale nonce into cached HTML. Enable your cache plugin's AJAX/nonce-refresh exclusion, or exclude `/wp-json/` from the cache. |
| Geolocation always fails | Check the provider's API key (if required) and that outbound HTTP requests aren't blocked by a firewall; test via **Debug & Help → Run Detection Test**, which surfaces the underlying error to admins. |

---

## 11. Example location configuration

| Location | City | State/Region | Country | Order URL |
|---|---|---|---|---|
| Arizona | Phoenix | Arizona | United States (US) | `https://example.com/arizona-order` |
| Manchester | Manchester | England | United Kingdom (GB) | `https://example.com/manchester-order` |
| Flamingo | Las Vegas | Nevada | United States (US) | `https://example.com/flamingo-order` |

Enter these on the **Locations** tab (none are pre-seeded by the plugin — locations are entirely admin-managed, per spec).

---

## 12. Getting updates

This plugin is not published on WordPress.org, so it ships its own **self-hosted update checker** (`includes/class-rlr-updater.php`, powered by the vendored [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) library) pointed at its GitHub repository:

**https://github.com/chatgpta67-blip/restaurant-location-redirect**

Once installed, the site checks that repo's Releases periodically (same schedule/UI as a WordPress.org plugin) and shows a normal **"There is a new version of Restaurant Location Order Redirect available"** notice on the Plugins page, with a one-click **Update Now** — no manual re-upload needed. Because the repo is public, no access token is required.

### Cutting a new release (for whoever maintains the code)

1. Make your changes, then bump the version in **two places** in `restaurant-location-redirect.php`: the `Version:` line in the header comment, and the `define( 'RLR_VERSION', '...' )` constant. They must match.
2. Run the build/release script from the plugin folder:
   ```powershell
   ./bin/build-zip.ps1
   ```
   This stages only the production files (excluding `tests/`, `docs/`, `node_modules/`, etc. — the same list as `.distignore`), zips them, and publishes a GitHub Release tagged `vX.Y.Z` with that ZIP attached as a release asset. Sites running an older version will see the update notice within a few hours (WordPress's own update-check cron interval), or immediately via **Dashboard → Updates → Check Again**.
3. Commit and push the source changes separately (`git add -A && git commit && git push`) so the repository's `main` branch and the tagged release stay in sync.

Run `./bin/build-zip.ps1 -SkipRelease` to build the ZIP locally without touching GitHub (e.g. for manual testing).

### If you'd rather host it elsewhere

Change the `RLR_GITHUB_REPO_URL` constant near the top of `restaurant-location-redirect.php` to point at a different GitHub repository (e.g. a private fork). For a private repo, also define `RLR_GITHUB_ACCESS_TOKEN` (a GitHub personal access token with `repo` read scope) — ideally in `wp-config.php`, not in the plugin file itself, so it isn't committed to version control.

---

## 13. Plugin structure

```
restaurant-location-redirect/
├── restaurant-location-redirect.php   Bootstrap: constants, includes, activation hooks
├── uninstall.php                       Opt-in data removal
├── readme.txt                          WordPress.org-style readme
├── includes/
│   ├── class-rlr-plugin.php            Orchestrator — wires admin/public/REST
│   ├── class-rlr-activator.php         Creates tables, seeds defaults, schedules cron
│   ├── class-rlr-deactivator.php       Clears cron
│   ├── class-rlr-settings.php          Defaults + sanitization
│   ├── class-rlr-location-manager.php  Locations CRUD (custom table)
│   ├── class-rlr-geolocation.php       Provider orchestration, caching, rate limiting
│   ├── interface-rlr-geolocation-provider.php
│   ├── providers/class-rlr-provider-ipapi.php
│   ├── providers/class-rlr-provider-ipinfo.php
│   ├── class-rlr-matcher.php           City→state→country→proximity confidence matching
│   ├── class-rlr-analytics.php         Event storage, retention, dashboard aggregation
│   ├── class-rlr-rest.php              /locations, /detect, /track REST endpoints
│   ├── class-rlr-admin.php             Settings page, tabs, CRUD form handlers
│   ├── class-rlr-public.php            Asset enqueue, modal render, shortcodes
│   ├── class-rlr-updater.php           GitHub-hosted "update available" notice
│   ├── class-rlr-helpers.php           IP/device/URL/referrer utilities
│   └── libs/plugin-update-checker/     Vendored 3rd-party library (MIT license)
├── admin/{css,js,views}/               Admin UI assets + tab templates
├── public/{css,js}/                    Frontend modal styling + RlrController
├── templates/location-modal.php        Visitor-agnostic modal markup
├── bin/build-zip.ps1                   Build + publish a release ZIP (see §12)
├── tests/php/                          PHPUnit (WP core test suite)
├── tests/js/                           Jest + jsdom
└── docs/README.md                      This document
```

All classes/functions/hooks use the `RLR_`/`rlr_` prefix to avoid collisions with other plugins or themes.

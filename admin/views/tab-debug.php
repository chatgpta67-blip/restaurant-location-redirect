<?php
/**
 * Debug & Help tab.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<h2><?php esc_html_e( 'Test Your Own Detection', 'restaurant-location-redirect' ); ?></h2>
<p><?php esc_html_e( 'Runs the same geolocation + matching logic a visitor would trigger, using your current IP address. Results are only ever shown to administrators.', 'restaurant-location-redirect' ); ?></p>
<p>
	<button type="button" class="button button-secondary" id="rlr-test-detect"><?php esc_html_e( 'Run Detection Test', 'restaurant-location-redirect' ); ?></button>
</p>
<pre id="rlr-test-detect-output" class="rlr-debug-output" hidden></pre>

<?php if ( empty( $settings['debug_mode'] ) ) : ?>
	<div class="notice notice-warning inline">
		<p><?php esc_html_e( 'Tip: enable Debug Mode in the General tab to also see a live debug panel on the frontend (visible to administrators only).', 'restaurant-location-redirect' ); ?></p>
	</div>
<?php endif; ?>

<hr />

<h2><?php esc_html_e( 'Simulate a Detected Location', 'restaurant-location-redirect' ); ?></h2>
<p>
	<?php esc_html_e( 'Tests the matching logic (city → state → country → proximity, and your confidence threshold) against any city/state/country you type in — without calling the real geolocation API and without needing to actually be in that location or use a VPN. Useful for verifying your configured locations match correctly from anywhere in the world.', 'restaurant-location-redirect' ); ?>
</p>
<table class="form-table rlr-simulate-form" role="presentation">
	<tr>
		<th scope="row"><label for="rlr-sim-city"><?php esc_html_e( 'City', 'restaurant-location-redirect' ); ?></label></th>
		<td><input type="text" id="rlr-sim-city" class="regular-text" placeholder="Phoenix" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="rlr-sim-state"><?php esc_html_e( 'State / Region', 'restaurant-location-redirect' ); ?></label></th>
		<td><input type="text" id="rlr-sim-state" class="regular-text" placeholder="Arizona" /></td>
	</tr>
	<tr>
		<th scope="row"><label for="rlr-sim-country"><?php esc_html_e( 'Country / Code', 'restaurant-location-redirect' ); ?></label></th>
		<td>
			<input type="text" id="rlr-sim-country" class="regular-text" placeholder="United States" />
			<input type="text" id="rlr-sim-country-code" class="small-text" maxlength="2" placeholder="US" style="text-transform:uppercase" />
		</td>
	</tr>
	<tr>
		<th scope="row"><label for="rlr-sim-lat"><?php esc_html_e( 'Latitude / Longitude', 'restaurant-location-redirect' ); ?></label></th>
		<td>
			<input type="text" id="rlr-sim-lat" class="small-text" placeholder="33.4484" />
			<input type="text" id="rlr-sim-lng" class="small-text" placeholder="-112.0740" />
			<p class="description"><?php esc_html_e( 'Optional — only used if Proximity Matching is enabled on the Geolocation tab.', 'restaurant-location-redirect' ); ?></p>
		</td>
	</tr>
</table>
<p>
	<button type="button" class="button button-primary" id="rlr-simulate-run"><?php esc_html_e( 'Simulate', 'restaurant-location-redirect' ); ?></button>
	<button type="button" class="button" data-rlr-sim-example="phoenix"><?php esc_html_e( 'Fill: Phoenix, AZ, US', 'restaurant-location-redirect' ); ?></button>
	<button type="button" class="button" data-rlr-sim-example="manchester"><?php esc_html_e( 'Fill: Manchester, England, GB', 'restaurant-location-redirect' ); ?></button>
</p>
<pre id="rlr-simulate-output" class="rlr-debug-output" hidden></pre>

<hr />

<h2><?php esc_html_e( 'Data & Privacy', 'restaurant-location-redirect' ); ?></h2>
<ul class="rlr-doc-list">
	<li><?php esc_html_e( 'IP addresses are used only transiently to perform a geolocation lookup, and are never permanently stored. A one-way, salted hash of the IP is kept briefly (as a WordPress transient) purely for result caching and abuse-rate-limiting, then expires automatically.', 'restaurant-location-redirect' ); ?></li>
	<li><?php esc_html_e( 'The visitor\'s selected location is stored client-side only, as a first-party cookie and/or localStorage entry containing a location slug — no names, emails, or precise coordinates.', 'restaurant-location-redirect' ); ?></li>
	<li><?php esc_html_e( 'Analytics (when enabled) stores aggregated event counts: an event type, an optional location id, match method, confidence level, device category, referrer category, and a randomly generated session id that is not derived from any personal data.', 'restaurant-location-redirect' ); ?></li>
	<li><?php esc_html_e( 'The visitor\'s IP address is never included in analytics records, and is never exposed in any public-facing HTML or API response — the debug panel/tool on this page is restricted to administrators.', 'restaurant-location-redirect' ); ?></li>
	<li><?php esc_html_e( 'Geolocation API keys are stored in the WordPress options table and used only in server-side requests (via wp_remote_get); they are never printed into frontend HTML or JavaScript.', 'restaurant-location-redirect' ); ?></li>
</ul>

<h2><?php esc_html_e( 'Troubleshooting', 'restaurant-location-redirect' ); ?></h2>
<dl class="rlr-doc-list">
	<dt><?php esc_html_e( 'Buttons are not being updated', 'restaurant-location-redirect' ); ?></dt>
	<dd><?php esc_html_e( 'Confirm the CSS selector in General settings actually matches your Order Now buttons (inspect the element in your browser), and that the plugin is enabled.', 'restaurant-location-redirect' ); ?></dd>

	<dt><?php esc_html_e( 'The popup never appears', 'restaurant-location-redirect' ); ?></dt>
	<dd><?php esc_html_e( 'Check that "Location Popup" is enabled, that at least one location is Active, and that browser devtools console has no JavaScript errors from other plugins/theme scripts.', 'restaurant-location-redirect' ); ?></dd>

	<dt><?php esc_html_e( 'One visitor sees another visitor\'s location', 'restaurant-location-redirect' ); ?></dt>
	<dd><?php esc_html_e( 'This plugin never renders visitor-specific HTML server-side, so this should not happen even behind full-page caching. If it does, check that a caching/CDN layer is not caching the /wp-json/rlr/v1/detect REST response itself (it is sent with no-store headers) or serving a stale full page that never re-runs the plugin\'s JavaScript.', 'restaurant-location-redirect' ); ?></dd>

	<dt><?php esc_html_e( 'REST requests return 401/403', 'restaurant-location-redirect' ); ?></dt>
	<dd><?php esc_html_e( 'Some aggressive caching plugins cache the page-embedded nonce. Enable your cache plugin\'s "nonce refresh" / exclude-logged-in-actions feature, or exclude wp-json requests from the cache.', 'restaurant-location-redirect' ); ?></dd>
</dl>

<script>
( function () {
	var btn = document.getElementById( 'rlr-test-detect' );
	var out = document.getElementById( 'rlr-test-detect-output' );
	if ( ! btn || typeof rlrAdminConfig === 'undefined' ) {
		return;
	}
	btn.addEventListener( 'click', function () {
		out.hidden = false;
		out.textContent = '<?php echo esc_js( __( 'Running…', 'restaurant-location-redirect' ) ); ?>';
		fetch( rlrAdminConfig.restUrl + '/detect', { credentials: 'same-origin' } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { out.textContent = JSON.stringify( data, null, 2 ); } )
			.catch( function ( e ) { out.textContent = 'Error: ' + e; } );
	} );
} )();

( function () {
	if ( typeof rlrAdminConfig === 'undefined' ) {
		return;
	}

	var examples = {
		phoenix: { city: 'Phoenix', state: 'Arizona', country: 'United States', code: 'US' },
		manchester: { city: 'Manchester', state: 'England', country: 'United Kingdom', code: 'GB' }
	};

	document.querySelectorAll( '[data-rlr-sim-example]' ).forEach( function ( el ) {
		el.addEventListener( 'click', function () {
			var e = examples[ el.getAttribute( 'data-rlr-sim-example' ) ];
			if ( ! e ) { return; }
			document.getElementById( 'rlr-sim-city' ).value = e.city;
			document.getElementById( 'rlr-sim-state' ).value = e.state;
			document.getElementById( 'rlr-sim-country' ).value = e.country;
			document.getElementById( 'rlr-sim-country-code' ).value = e.code;
		} );
	} );

	var runBtn = document.getElementById( 'rlr-simulate-run' );
	var simOut = document.getElementById( 'rlr-simulate-output' );
	if ( ! runBtn ) {
		return;
	}

	runBtn.addEventListener( 'click', function () {
		simOut.hidden = false;
		simOut.textContent = '<?php echo esc_js( __( 'Running…', 'restaurant-location-redirect' ) ); ?>';

		var payload = {
			city: document.getElementById( 'rlr-sim-city' ).value,
			state: document.getElementById( 'rlr-sim-state' ).value,
			country: document.getElementById( 'rlr-sim-country' ).value,
			country_code: document.getElementById( 'rlr-sim-country-code' ).value,
			latitude: document.getElementById( 'rlr-sim-lat' ).value,
			longitude: document.getElementById( 'rlr-sim-lng' ).value
		};

		fetch( rlrAdminConfig.restUrl + '/simulate', {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': rlrAdminConfig.nonce },
			body: JSON.stringify( payload )
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( data ) { simOut.textContent = JSON.stringify( data, null, 2 ); } )
			.catch( function ( e ) { simOut.textContent = 'Error: ' + e; } );
	} );
} )();
</script>

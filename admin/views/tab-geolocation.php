<?php
/**
 * Geolocation settings tab.
 *
 * @var array $settings Current settings.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$providers = RLR_Geolocation::get_available_providers();
?>
<form method="post" action="options.php">
	<?php settings_fields( 'rlr_settings_group' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Geolocation Provider', 'restaurant-location-redirect' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[geo_provider]" id="rlr-geo-provider">
					<?php foreach ( $providers as $id => $provider ) : ?>
						<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $settings['geo_provider'], $id ); ?>>
							<?php echo esc_html( $provider->get_label() ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'The provider used for server-side IP lookups. API keys are stored securely and never sent to the browser.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>

		<?php foreach ( $providers as $id => $provider ) : ?>
			<?php if ( ! $provider->requires_api_key() ) { continue; } ?>
			<tr class="rlr-provider-key-row" data-provider="<?php echo esc_attr( $id ); ?>">
				<th scope="row">
					<?php
					/* translators: %s: provider name */
					printf( esc_html__( '%s API Key', 'restaurant-location-redirect' ), esc_html( $provider->get_label() ) );
					?>
				</th>
				<td>
					<input type="password" autocomplete="off" class="regular-text"
						name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[geo_api_keys][<?php echo esc_attr( $id ); ?>]"
						value="<?php echo esc_attr( isset( $settings['geo_api_keys'][ $id ] ) ? $settings['geo_api_keys'][ $id ] : '' ); ?>" />
				</td>
			</tr>
		<?php endforeach; ?>

		<tr>
			<th scope="row"><?php esc_html_e( 'Result Cache Duration', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="number" min="0" max="720" class="small-text"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[geo_cache_duration_hours]"
					value="<?php echo esc_attr( $settings['geo_cache_duration_hours'] ); ?>" />
				<?php esc_html_e( 'hours', 'restaurant-location-redirect' ); ?>
				<p class="description"><?php esc_html_e( 'How long a lookup result for a given IP is cached (via transients) to reduce API usage. Set to 0 to disable caching.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Request Timeout', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="number" min="1" max="30" class="small-text"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[geo_request_timeout]"
					value="<?php echo esc_attr( $settings['geo_request_timeout'] ); ?>" />
				<?php esc_html_e( 'seconds', 'restaurant-location-redirect' ); ?>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Rate Limit', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="number" min="1" max="1000" class="small-text"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[geo_rate_limit_per_hour]"
					value="<?php echo esc_attr( $settings['geo_rate_limit_per_hour'] ); ?>" />
				<?php esc_html_e( 'lookups per hour, per visitor IP', 'restaurant-location-redirect' ); ?>
				<p class="description"><?php esc_html_e( 'Protects your API quota/costs from abuse. Lookups beyond this limit fall back to the manual selector.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Confidence Threshold', 'restaurant-location-redirect' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[confidence_threshold]">
					<option value="low" <?php selected( $settings['confidence_threshold'], 'low' ); ?>><?php esc_html_e( 'Low — auto-select on country match alone', 'restaurant-location-redirect' ); ?></option>
					<option value="medium" <?php selected( $settings['confidence_threshold'], 'medium' ); ?>><?php esc_html_e( 'Medium — require at least a state/region match (recommended)', 'restaurant-location-redirect' ); ?></option>
					<option value="high" <?php selected( $settings['confidence_threshold'], 'high' ); ?>><?php esc_html_e( 'High — require a city-level match', 'restaurant-location-redirect' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'The minimum confidence required to auto-select a location without asking the visitor. Anything below this shows the location popup instead.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Proximity Matching', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[proximity_enabled]" value="1" <?php checked( $settings['proximity_enabled'] ); ?> />
					<?php esc_html_e( 'Use latitude/longitude proximity as a fallback or tie-breaker when city/state/country text doesn\'t resolve to a single location.', 'restaurant-location-redirect' ); ?>
				</label>
				<br /><br />
				<label>
					<?php esc_html_e( 'Radius:', 'restaurant-location-redirect' ); ?>
					<input type="number" min="1" max="500" class="small-text"
						name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[proximity_radius_km]"
						value="<?php echo esc_attr( $settings['proximity_radius_km'] ); ?>" />
					km
				</label>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>

<h2><?php esc_html_e( 'Adding another provider', 'restaurant-location-redirect' ); ?></h2>
<p>
	<?php esc_html_e( 'Developers can register additional geolocation providers by implementing RLR_Geolocation_Provider and hooking into the rlr_geolocation_provider_classes filter — no core plugin files need to be modified.', 'restaurant-location-redirect' ); ?>
</p>

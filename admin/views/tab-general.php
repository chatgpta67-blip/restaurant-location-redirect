<?php
/**
 * General settings tab.
 *
 * @var array $settings Current settings (from RLR_Admin::render_page()).
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form method="post" action="options.php">
	<?php settings_fields( 'rlr_settings_group' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Enable Plugin', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( $settings['enabled'] ); ?> />
					<?php esc_html_e( 'Enable location detection and Order Now button redirection on the frontend.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="rlr-button-selector"><?php esc_html_e( 'Order Button CSS Selector', 'restaurant-location-redirect' ); ?></label>
			</th>
			<td>
				<input type="text" id="rlr-button-selector" class="regular-text code"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[button_selector]"
					value="<?php echo esc_attr( $settings['button_selector'] ); ?>" />
				<p class="description">
					<?php esc_html_e( 'Any valid CSS selector, comma-separated for multiple. Examples: .order-now, .order-now-button, a[data-role="order-now"]', 'restaurant-location-redirect' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row">
				<label for="rlr-change-selector"><?php esc_html_e( 'Change Location Trigger Selector', 'restaurant-location-redirect' ); ?></label>
			</th>
			<td>
				<input type="text" id="rlr-change-selector" class="regular-text code"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[change_location_selector]"
					value="<?php echo esc_attr( $settings['change_location_selector'] ); ?>" />
				<p class="description">
					<?php
					printf(
						/* translators: %s: shortcode */
						esc_html__( 'Any element matching this selector re-opens the location popup when clicked. You can also use the %s shortcode.', 'restaurant-location-redirect' ),
						'<code>[rlr_change_location]</code>'
					);
					?>
				</p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Storage Duration', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="number" min="1" max="3650" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[storage_duration_days]"
					value="<?php echo esc_attr( $settings['storage_duration_days'] ); ?>" class="small-text" />
				<?php esc_html_e( 'days', 'restaurant-location-redirect' ); ?>
				<p class="description"><?php esc_html_e( 'How long a visitor\'s selected/detected location is remembered. Default: 30 days.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Storage Method', 'restaurant-location-redirect' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[storage_method]">
					<option value="both" <?php selected( $settings['storage_method'], 'both' ); ?>><?php esc_html_e( 'Cookie + localStorage (recommended)', 'restaurant-location-redirect' ); ?></option>
					<option value="cookie" <?php selected( $settings['storage_method'], 'cookie' ); ?>><?php esc_html_e( 'Cookie only', 'restaurant-location-redirect' ); ?></option>
					<option value="localstorage" <?php selected( $settings['storage_method'], 'localstorage' ); ?>><?php esc_html_e( 'localStorage only', 'restaurant-location-redirect' ); ?></option>
				</select>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Automatic Geolocation', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[enable_geolocation]" value="1" <?php checked( $settings['enable_geolocation'] ); ?> />
					<?php esc_html_e( 'Attempt to detect the visitor\'s location via IP geolocation when no location is saved yet.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Location Popup', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[enable_popup]" value="1" <?php checked( $settings['enable_popup'] ); ?> />
					<?php esc_html_e( 'Show the "Select Your Location" popup when the location cannot be confidently determined.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Debug Mode', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[debug_mode]" value="1" <?php checked( $settings['debug_mode'] ); ?> />
					<?php esc_html_e( 'Show a debug panel with detection details on the frontend — visible only to logged-in administrators.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>

		<tr>
			<th scope="row"><?php esc_html_e( 'Uninstall Behavior', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="rlr_remove_data_on_uninstall" value="1" <?php checked( (bool) get_option( 'rlr_remove_data_on_uninstall', false ) ); ?> />
					<?php esc_html_e( 'Permanently delete all locations, settings, and analytics data when this plugin is deleted.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>
	</table>

	<?php submit_button(); ?>
</form>

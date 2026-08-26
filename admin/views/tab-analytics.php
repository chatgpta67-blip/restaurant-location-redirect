<?php
/**
 * Analytics dashboard tab.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$today       = current_time( 'Y-m-d' );
$default_start = gmdate( 'Y-m-d', strtotime( '-29 days', strtotime( $today ) ) );

$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : $default_start; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : $today; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start_date ) ) {
	$start_date = $default_start;
}
if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end_date ) ) {
	$end_date = $today;
}

if ( 'disabled' === $settings['analytics_mode'] ) {
	?>
	<div class="notice notice-info inline">
		<p>
			<?php
			printf(
				/* translators: %s: link to General/Analytics settings */
				esc_html__( 'Analytics is currently disabled. Enable it in the %s to start collecting privacy-minimized, aggregated events.', 'restaurant-location-redirect' ),
				'<a href="' . esc_url( add_query_arg( array( 'page' => 'rlr-settings', 'tab' => 'analytics' ), admin_url( 'options-general.php' ) ) ) . '#rlr-analytics-settings">' . esc_html__( 'Analytics settings below', 'restaurant-location-redirect' ) . '</a>'
			);
			?>
		</p>
	</div>
	<?php
}

$data = RLR_Analytics::get_dashboard_data( $start_date, $end_date );
$totals = $data['totals'];
?>
<h2><?php esc_html_e( 'Dashboard', 'restaurant-location-redirect' ); ?></h2>

<form method="get" class="rlr-analytics-filter">
	<input type="hidden" name="page" value="rlr-settings" />
	<input type="hidden" name="tab" value="analytics" />
	<label>
		<?php esc_html_e( 'From', 'restaurant-location-redirect' ); ?>
		<input type="date" name="start_date" value="<?php echo esc_attr( $start_date ); ?>" />
	</label>
	<label>
		<?php esc_html_e( 'To', 'restaurant-location-redirect' ); ?>
		<input type="date" name="end_date" value="<?php echo esc_attr( $end_date ); ?>" />
	</label>
	<button type="submit" class="button"><?php esc_html_e( 'Filter', 'restaurant-location-redirect' ); ?></button>
</form>

<div class="rlr-stat-cards">
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['order_click'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Order Button Clicks', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['auto_match'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Automatic Matches', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['manual_selection'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Manual Selections', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['location_changed'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Location Changes', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['geolocation_failure'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Geolocation Failures', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo esc_html( $totals['no_confident_match'] ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'No-Confident-Match Events', 'restaurant-location-redirect' ); ?></span>
	</div>
	<div class="rlr-stat-card">
		<span class="rlr-stat-number"><?php echo null === $data['conversion_rate'] ? '—' : esc_html( $data['conversion_rate'] . '%' ); ?></span>
		<span class="rlr-stat-label"><?php esc_html_e( 'Selection → Order Click Rate', 'restaurant-location-redirect' ); ?></span>
	</div>
</div>

<h3><?php esc_html_e( 'By Location', 'restaurant-location-redirect' ); ?></h3>
<table class="widefat striped">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Location', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Auto Matches', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Manual Selections', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Order Clicks', 'restaurant-location-redirect' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $data['per_location'] ) ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No location-level events in this range.', 'restaurant-location-redirect' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $data['per_location'] as $loc_id => $loc_data ) : ?>
			<tr>
				<td><?php echo esc_html( $loc_data['name'] ); ?></td>
				<td><?php echo esc_html( $loc_data['stats']['auto_match'] ); ?></td>
				<td><?php echo esc_html( $loc_data['stats']['manual_selection'] ); ?></td>
				<td><?php echo esc_html( $loc_data['stats']['order_click'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<h3><?php esc_html_e( 'Daily Summary', 'restaurant-location-redirect' ); ?></h3>
<div class="rlr-daily-chart">
	<?php if ( empty( $data['daily'] ) ) : ?>
		<p><?php esc_html_e( 'No events in this range.', 'restaurant-location-redirect' ); ?></p>
	<?php else : ?>
		<?php
		$max = 1;
		foreach ( $data['daily'] as $day_stats ) {
			$max = max( $max, $day_stats['order_click'] + $day_stats['auto_match'] + $day_stats['manual_selection'] );
		}
		?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'restaurant-location-redirect' ); ?></th>
					<th><?php esc_html_e( 'Matches', 'restaurant-location-redirect' ); ?></th>
					<th><?php esc_html_e( 'Order Clicks', 'restaurant-location-redirect' ); ?></th>
					<th style="width:40%"><?php esc_html_e( 'Volume', 'restaurant-location-redirect' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $data['daily'] as $day => $day_stats ) : ?>
				<?php
				$matches = $day_stats['auto_match'] + $day_stats['manual_selection'];
				$volume  = $matches + $day_stats['order_click'];
				$pct     = $max > 0 ? round( ( $volume / $max ) * 100 ) : 0;
				?>
				<tr>
					<td><?php echo esc_html( $day ); ?></td>
					<td><?php echo esc_html( $matches ); ?></td>
					<td><?php echo esc_html( $day_stats['order_click'] ); ?></td>
					<td><div class="rlr-bar" style="width: <?php echo esc_attr( $pct ); ?>%;"></div></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<hr />

<h2 id="rlr-analytics-settings"><?php esc_html_e( 'Analytics Settings', 'restaurant-location-redirect' ); ?></h2>

<form method="post" action="options.php">
	<?php settings_fields( 'rlr_settings_group' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Analytics Mode', 'restaurant-location-redirect' ); ?></th>
			<td>
				<select name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[analytics_mode]">
					<option value="disabled" <?php selected( $settings['analytics_mode'], 'disabled' ); ?>><?php esc_html_e( 'Disabled (default)', 'restaurant-location-redirect' ); ?></option>
					<option value="internal" <?php selected( $settings['analytics_mode'], 'internal' ); ?>><?php esc_html_e( 'Internal aggregated analytics', 'restaurant-location-redirect' ); ?></option>
					<option value="internal_external" <?php selected( $settings['analytics_mode'], 'internal_external' ); ?>><?php esc_html_e( 'Internal + external (GA4)', 'restaurant-location-redirect' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Require Consent', 'restaurant-location-redirect' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[analytics_require_consent]" value="1" <?php checked( $settings['analytics_require_consent'] ); ?> />
					<?php esc_html_e( 'Do not send any analytics events until visitor consent is detected.', 'restaurant-location-redirect' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Consent Cookie', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="text" class="regular-text" placeholder="<?php esc_attr_e( 'cookie name, e.g. cookie_consent', 'restaurant-location-redirect' ); ?>"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[analytics_consent_cookie_name]"
					value="<?php echo esc_attr( $settings['analytics_consent_cookie_name'] ); ?>" />
				<input type="text" class="regular-text" placeholder="<?php esc_attr_e( 'required value/substring, optional', 'restaurant-location-redirect' ); ?>"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[analytics_consent_cookie_value]"
					value="<?php echo esc_attr( $settings['analytics_consent_cookie_value'] ); ?>" />
				<p class="description">
					<?php esc_html_e( 'Used to detect consent from your existing cookie/consent-management plugin. For full control, define window.rlrConsentCheck = function(){ return true/false; } in your theme instead.', 'restaurant-location-redirect' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'GA4 Measurement ID', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="text" class="regular-text" placeholder="G-XXXXXXXXXX"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[ga4_measurement_id]"
					value="<?php echo esc_attr( $settings['ga4_measurement_id'] ); ?>" />
				<p class="description"><?php esc_html_e( 'Optional, for reference only. This plugin does not load gtag.js — it pushes events through your site\'s existing gtag()/dataLayer if present, and never sends raw IP addresses or coordinates.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Retention Period', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="number" min="1" max="3650" class="small-text"
					name="<?php echo esc_attr( RLR_Settings::OPTION_KEY ); ?>[analytics_retention_days]"
					value="<?php echo esc_attr( $settings['analytics_retention_days'] ); ?>" />
				<?php esc_html_e( 'days — events older than this are deleted automatically by a daily cron job.', 'restaurant-location-redirect' ); ?>
			</td>
		</tr>
	</table>
	<?php submit_button( __( 'Save Analytics Settings', 'restaurant-location-redirect' ) ); ?>
</form>

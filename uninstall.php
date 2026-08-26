<?php
/**
 * Uninstall handler.
 *
 * Only removes data when the admin has explicitly opted in via the
 * "Remove all data on uninstall" setting (General tab). Deactivating or
 * simply deleting the plugin without that option leaves all locations,
 * settings, and analytics data intact for a future reinstall.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$rlr_should_remove_data = get_option( 'rlr_remove_data_on_uninstall', false );

if ( ! $rlr_should_remove_data ) {
	return;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rlr_locations" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}rlr_analytics_events" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'rlr_settings' );
delete_option( 'rlr_remove_data_on_uninstall' );
delete_option( 'rlr_db_version' );
delete_option( 'rlr_activated_at' );

$timestamp = wp_next_scheduled( 'rlr_cleanup_analytics' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'rlr_cleanup_analytics' );
}

// Clean up any leftover geolocation/rate-limit transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->esc_like( '_transient_rlr_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_rlr_' ) . '%'
	)
); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

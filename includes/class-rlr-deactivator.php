<?php
/**
 * Runs on plugin deactivation. Data is intentionally preserved; use the
 * "Remove all data on uninstall" setting + uninstall.php for full removal.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Deactivator {

	/**
	 * Deactivation callback.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'rlr_cleanup_analytics' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rlr_cleanup_analytics' );
		}
	}
}

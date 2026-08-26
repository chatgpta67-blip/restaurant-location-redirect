<?php
/**
 * Runs on plugin activation: creates DB tables and seeds default options.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Activator {

	/**
	 * Activation callback.
	 */
	public static function activate() {
		self::create_tables();
		self::seed_defaults();
		self::maybe_schedule_cron();

		update_option( 'rlr_db_version', RLR_DB_VERSION );

		// Flag so the frontend knows to flush any cache-plugin-side rewrite/opcode caches if needed.
		if ( ! get_option( 'rlr_activated_at' ) ) {
			update_option( 'rlr_activated_at', time() );
		}
	}

	/**
	 * Create/upgrade custom tables using dbDelta (idempotent).
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$locations_table = $wpdb->prefix . 'rlr_locations';
		$sql_locations   = "CREATE TABLE {$locations_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(191) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			city VARCHAR(191) NOT NULL DEFAULT '',
			state VARCHAR(191) NOT NULL DEFAULT '',
			country VARCHAR(191) NOT NULL DEFAULT '',
			country_code VARCHAR(4) NOT NULL DEFAULT '',
			order_url TEXT NOT NULL,
			latitude DECIMAL(10,7) NULL DEFAULT NULL,
			longitude DECIMAL(10,7) NULL DEFAULT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			sort_order INT NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			updated_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status),
			KEY city (city),
			KEY state (state),
			KEY country (country)
		) {$charset_collate};";

		$events_table = $wpdb->prefix . 'rlr_analytics_events';
		$sql_events   = "CREATE TABLE {$events_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_type VARCHAR(50) NOT NULL,
			location_id BIGINT UNSIGNED NULL DEFAULT NULL,
			match_method VARCHAR(20) NULL DEFAULT NULL,
			confidence VARCHAR(20) NULL DEFAULT NULL,
			session_hash VARCHAR(64) NULL DEFAULT NULL,
			device_category VARCHAR(20) NULL DEFAULT NULL,
			referrer_category VARCHAR(50) NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT '1970-01-01 00:00:01',
			PRIMARY KEY  (id),
			KEY event_type (event_type),
			KEY location_id (location_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_locations );
		dbDelta( $sql_events );
	}

	/**
	 * Seed default settings if none exist yet. Does not create example
	 * locations automatically -- the admin must add their own, per spec.
	 */
	private static function seed_defaults() {
		if ( false === get_option( RLR_Settings::OPTION_KEY, false ) ) {
			update_option( RLR_Settings::OPTION_KEY, RLR_Settings::defaults(), false );
		}
	}

	/**
	 * Schedule the daily analytics retention cleanup cron event.
	 */
	private static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'rlr_cleanup_analytics' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'rlr_cleanup_analytics' );
		}
	}
}

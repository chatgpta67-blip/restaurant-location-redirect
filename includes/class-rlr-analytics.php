<?php
/**
 * Privacy-conscious analytics: event storage, aggregation for the admin
 * dashboard, and retention cleanup. Disabled by default; never stores raw
 * IP addresses, coordinates, or personal identifiers.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Analytics {

	/**
	 * Recognized event types.
	 *
	 * @var string[]
	 */
	const EVENT_TYPES = array(
		'auto_match',
		'manual_selection',
		'selector_opened',
		'location_changed',
		'geolocation_failure',
		'no_confident_match',
		'order_click',
	);

	/**
	 * Register hooks: cron cleanup and the generic integration action.
	 */
	public static function init() {
		add_action( 'rlr_cleanup_analytics', array( __CLASS__, 'run_retention_cleanup' ) );
	}

	/**
	 * Table name for analytics events.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rlr_analytics_events';
	}

	/**
	 * Whether analytics collection is currently enabled at all (internal
	 * or internal+external).
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$mode = RLR_Settings::get( 'analytics_mode', 'disabled' );
		return in_array( $mode, array( 'internal', 'internal_external' ), true );
	}

	/**
	 * Record a single event. Fails silently (returns false) on any error
	 * so analytics can never break the core redirect flow.
	 *
	 * @param array $event {
	 *     @type string $event_type
	 *     @type int|null $location_id
	 *     @type string|null $match_method
	 *     @type string|null $confidence
	 *     @type string|null $session_hash
	 *     @type string $device_category
	 *     @type string $referrer_category
	 * }
	 * @return bool
	 */
	public static function record( array $event ) {
		try {
			if ( ! self::is_enabled() ) {
				return false;
			}

			$type = isset( $event['event_type'] ) ? sanitize_key( $event['event_type'] ) : '';
			if ( ! in_array( $type, self::EVENT_TYPES, true ) ) {
				return false;
			}

			global $wpdb;

			$location_id = isset( $event['location_id'] ) && $event['location_id'] ? absint( $event['location_id'] ) : null;

			$match_method = isset( $event['match_method'] ) ? sanitize_key( $event['match_method'] ) : null;
			if ( $match_method && ! in_array( $match_method, array( 'city', 'state', 'country', 'proximity', 'manual', 'none' ), true ) ) {
				$match_method = null;
			}

			$confidence = isset( $event['confidence'] ) ? sanitize_key( $event['confidence'] ) : null;
			if ( $confidence && ! in_array( $confidence, array( 'none', 'low', 'medium', 'high' ), true ) ) {
				$confidence = null;
			}

			$session_hash = isset( $event['session_hash'] ) ? preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) $event['session_hash'] ) : null;
			$session_hash = $session_hash ? substr( $session_hash, 0, 64 ) : null;

			$device_category = isset( $event['device_category'] ) ? sanitize_key( $event['device_category'] ) : 'unknown';
			if ( ! in_array( $device_category, array( 'desktop', 'mobile', 'tablet', 'unknown' ), true ) ) {
				$device_category = 'unknown';
			}

			$referrer_category = isset( $event['referrer_category'] ) ? sanitize_key( $event['referrer_category'] ) : 'direct';
			if ( ! in_array( $referrer_category, array( 'direct', 'internal', 'search', 'social', 'referral' ), true ) ) {
				$referrer_category = 'direct';
			}

			$inserted = $wpdb->insert(
				self::table(),
				array(
					'event_type'         => $type,
					'location_id'        => $location_id,
					'match_method'       => $match_method,
					'confidence'         => $confidence,
					'session_hash'       => $session_hash,
					'device_category'    => $device_category,
					'referrer_category'  => $referrer_category,
					'created_at'         => current_time( 'mysql', true ),
				),
				array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				/**
				 * Fires after an analytics event is recorded, and also when
				 * analytics storage is disabled but an event occurred -- see
				 * RLR_REST::track_event() which fires this unconditionally.
				 *
				 * @param array $event Event payload.
				 */
				do_action( 'rlr_location_event', $event );
			}

			return (bool) $inserted;
		} catch ( Throwable $e ) {
			return false;
		}
	}

	/**
	 * Run retention cleanup: delete events older than the configured
	 * retention window.
	 */
	public static function run_retention_cleanup() {
		global $wpdb;

		$days = max( 1, absint( RLR_Settings::get( 'analytics_retention_days', 90 ) ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE created_at < %s', $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Build the aggregated dashboard dataset for a date range.
	 *
	 * @param string $start_date Y-m-d, inclusive.
	 * @param string $end_date   Y-m-d, inclusive.
	 * @return array
	 */
	public static function get_dashboard_data( $start_date, $end_date ) {
		global $wpdb;

		$table = self::table();
		$start = gmdate( 'Y-m-d 00:00:00', strtotime( $start_date ) );
		$end   = gmdate( 'Y-m-d 23:59:59', strtotime( $end_date ) );

		$totals_by_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, COUNT(*) as cnt FROM {$table} WHERE created_at BETWEEN %s AND %s GROUP BY event_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start,
				$end
			),
			ARRAY_A
		);

		$by_location = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT location_id, event_type, COUNT(*) as cnt FROM {$table} WHERE created_at BETWEEN %s AND %s AND location_id IS NOT NULL GROUP BY location_id, event_type", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start,
				$end
			),
			ARRAY_A
		);

		$daily = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE(created_at) as day, event_type, COUNT(*) as cnt FROM {$table} WHERE created_at BETWEEN %s AND %s GROUP BY DATE(created_at), event_type ORDER BY day ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start,
				$end
			),
			ARRAY_A
		);

		$totals = array_fill_keys( self::EVENT_TYPES, 0 );
		foreach ( (array) $totals_by_type as $row ) {
			if ( isset( $totals[ $row['event_type'] ] ) ) {
				$totals[ $row['event_type'] ] = (int) $row['cnt'];
			}
		}

		$locations = array();
		foreach ( RLR_Location_Manager::get_all() as $loc ) {
			$locations[ $loc['id'] ] = $loc['name'];
		}

		$per_location = array();
		foreach ( (array) $by_location as $row ) {
			$loc_id = (int) $row['location_id'];
			if ( ! isset( $per_location[ $loc_id ] ) ) {
				$per_location[ $loc_id ] = array(
					'name'  => isset( $locations[ $loc_id ] ) ? $locations[ $loc_id ] : sprintf( '#%d', $loc_id ),
					'stats' => array_fill_keys( self::EVENT_TYPES, 0 ),
				);
			}
			$per_location[ $loc_id ]['stats'][ $row['event_type'] ] = (int) $row['cnt'];
		}

		$daily_series = array();
		foreach ( (array) $daily as $row ) {
			$day = $row['day'];
			if ( ! isset( $daily_series[ $day ] ) ) {
				$daily_series[ $day ] = array_fill_keys( self::EVENT_TYPES, 0 );
			}
			$daily_series[ $day ][ $row['event_type'] ] = (int) $row['cnt'];
		}
		ksort( $daily_series );

		$selections = $totals['auto_match'] + $totals['manual_selection'];
		$conversion = $selections > 0 ? round( ( $totals['order_click'] / $selections ) * 100, 1 ) : null;

		return array(
			'totals'        => $totals,
			'per_location'  => $per_location,
			'daily'         => $daily_series,
			'conversion_rate' => $conversion,
			'range'         => array(
				'start' => $start_date,
				'end'   => $end_date,
			),
		);
	}
}

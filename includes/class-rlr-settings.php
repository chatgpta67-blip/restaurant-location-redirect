<?php
/**
 * Settings storage, defaults, and sanitization.
 *
 * All general/geolocation/analytics settings live in a single autoloaded
 * option (rlr_settings) to keep the options table tidy. Locations are
 * stored in a dedicated table via RLR_Location_Manager.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Settings {

	const OPTION_KEY = 'rlr_settings';

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'                    => true,
			'button_selector'            => '.order-now, .order-now-button',
			'change_location_selector'   => '.rlr-change-location',
			'storage_duration_days'      => 30,
			'storage_method'             => 'both', // cookie|localstorage|both.
			'enable_geolocation'         => true,
			'enable_popup'               => true,
			'debug_mode'                 => false,

			// Geolocation.
			'geo_provider'               => 'ip-api',
			'geo_api_keys'               => array(
				'ip-api' => '',
				'ipinfo' => '',
			),
			'geo_cache_duration_hours'   => 12,
			'geo_request_timeout'        => 5,
			'confidence_threshold'       => 'medium', // low|medium|high.
			'proximity_enabled'          => true,
			'proximity_radius_km'        => 80,
			'geo_rate_limit_per_hour'    => 30,

			// Analytics.
			'analytics_mode'             => 'disabled', // disabled|internal|internal_external.
			'analytics_retention_days'   => 90,
			'analytics_require_consent'  => true,
			'analytics_consent_cookie_name'  => '',
			'analytics_consent_cookie_value' => '',
			'ga4_measurement_id'         => '',
		);
	}

	/**
	 * Get the current settings, merged with defaults so new keys added in
	 * future versions always have a sane fallback.
	 *
	 * @return array
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, self::defaults() );

		if ( isset( $stored['geo_api_keys'] ) && is_array( $stored['geo_api_keys'] ) ) {
			$settings['geo_api_keys'] = wp_parse_args( $stored['geo_api_keys'], self::defaults()['geo_api_keys'] );
		}

		return $settings;
	}

	/**
	 * Get a single setting by key.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if not found.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_all();
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Persist the full settings array (already sanitized).
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function update_all( array $settings ) {
		return update_option( self::OPTION_KEY, $settings, false );
	}

	/**
	 * Sanitize a raw settings submission (e.g. $_POST) against the schema.
	 * Unknown keys are dropped; missing keys fall back to current values.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized, complete settings array.
	 */
	public static function sanitize( array $input ) {
		$current = self::get_all();
		$out     = $current;

		$out['enabled']            = ! empty( $input['enabled'] );
		$out['enable_geolocation'] = ! empty( $input['enable_geolocation'] );
		$out['enable_popup']       = ! empty( $input['enable_popup'] );
		$out['debug_mode']         = ! empty( $input['debug_mode'] );
		$out['proximity_enabled']  = ! empty( $input['proximity_enabled'] );

		if ( isset( $input['button_selector'] ) ) {
			$out['button_selector'] = self::sanitize_css_selector( $input['button_selector'], $current['button_selector'] );
		}

		if ( isset( $input['change_location_selector'] ) ) {
			$out['change_location_selector'] = self::sanitize_css_selector( $input['change_location_selector'], $current['change_location_selector'] );
		}

		if ( isset( $input['storage_duration_days'] ) ) {
			$out['storage_duration_days'] = max( 1, min( 3650, absint( $input['storage_duration_days'] ) ) );
		}

		if ( isset( $input['storage_method'] ) && in_array( $input['storage_method'], array( 'cookie', 'localstorage', 'both' ), true ) ) {
			$out['storage_method'] = $input['storage_method'];
		}

		if ( isset( $input['geo_provider'] ) && in_array( $input['geo_provider'], array_keys( RLR_Geolocation::get_available_providers() ), true ) ) {
			$out['geo_provider'] = sanitize_key( $input['geo_provider'] );
		}

		if ( isset( $input['geo_api_keys'] ) && is_array( $input['geo_api_keys'] ) ) {
			foreach ( $out['geo_api_keys'] as $provider => $existing_key ) {
				if ( isset( $input['geo_api_keys'][ $provider ] ) ) {
					$out['geo_api_keys'][ $provider ] = sanitize_text_field( wp_unslash( $input['geo_api_keys'][ $provider ] ) );
				}
			}
		}

		if ( isset( $input['geo_cache_duration_hours'] ) ) {
			$out['geo_cache_duration_hours'] = max( 0, min( 720, absint( $input['geo_cache_duration_hours'] ) ) );
		}

		if ( isset( $input['geo_request_timeout'] ) ) {
			$out['geo_request_timeout'] = max( 1, min( 30, absint( $input['geo_request_timeout'] ) ) );
		}

		if ( isset( $input['confidence_threshold'] ) && in_array( $input['confidence_threshold'], array( 'low', 'medium', 'high' ), true ) ) {
			$out['confidence_threshold'] = $input['confidence_threshold'];
		}

		if ( isset( $input['proximity_radius_km'] ) ) {
			$out['proximity_radius_km'] = max( 1, min( 500, (float) $input['proximity_radius_km'] ) );
		}

		if ( isset( $input['geo_rate_limit_per_hour'] ) ) {
			$out['geo_rate_limit_per_hour'] = max( 1, min( 1000, absint( $input['geo_rate_limit_per_hour'] ) ) );
		}

		if ( isset( $input['analytics_mode'] ) && in_array( $input['analytics_mode'], array( 'disabled', 'internal', 'internal_external' ), true ) ) {
			$out['analytics_mode'] = $input['analytics_mode'];
		}

		if ( isset( $input['analytics_retention_days'] ) ) {
			$out['analytics_retention_days'] = max( 1, min( 3650, absint( $input['analytics_retention_days'] ) ) );
		}

		$out['analytics_require_consent'] = ! empty( $input['analytics_require_consent'] );

		if ( isset( $input['analytics_consent_cookie_name'] ) ) {
			$out['analytics_consent_cookie_name'] = sanitize_key( $input['analytics_consent_cookie_name'] );
		}

		if ( isset( $input['analytics_consent_cookie_value'] ) ) {
			$out['analytics_consent_cookie_value'] = sanitize_text_field( wp_unslash( $input['analytics_consent_cookie_value'] ) );
		}

		if ( isset( $input['ga4_measurement_id'] ) ) {
			$out['ga4_measurement_id'] = preg_replace( '/[^A-Za-z0-9\-]/', '', wp_unslash( $input['ga4_measurement_id'] ) );
		}

		return $out;
	}

	/**
	 * Restrict a CSS selector field to safe characters. Falls back to the
	 * previous value if the input looks unsafe (e.g. contains "<").
	 *
	 * @param string $raw      Raw selector input.
	 * @param string $fallback Previous value.
	 * @return string
	 */
	private static function sanitize_css_selector( $raw, $fallback ) {
		$raw = trim( wp_unslash( $raw ) );

		if ( '' === $raw ) {
			return $fallback;
		}

		// Allow letters, numbers, and common CSS selector syntax; strip anything else.
		if ( ! preg_match( '/^[a-zA-Z0-9\s\.\#\-_,>~\+\[\]="\':\(\)\*\^\$\|]+$/', $raw ) ) {
			return $fallback;
		}

		return sanitize_text_field( $raw );
	}
}

<?php
/**
 * Orchestrates IP geolocation lookups: provider selection, caching, and
 * rate limiting. Never exposes API keys to the frontend.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Geolocation {

	/**
	 * Registry of available providers. To add a new provider, implement
	 * RLR_Geolocation_Provider and add an entry here.
	 *
	 * @return array<string, RLR_Geolocation_Provider>
	 */
	public static function get_available_providers() {
		static $providers = null;

		if ( null === $providers ) {
			$providers = array();

			$classes = array(
				'RLR_Provider_IPAPI',
				'RLR_Provider_IPInfo',
			);

			/**
			 * Filter the list of geolocation provider class names available
			 * to the plugin, so other plugins/mu-plugins can register more.
			 *
			 * @param string[] $classes
			 */
			$classes = apply_filters( 'rlr_geolocation_provider_classes', $classes );

			foreach ( $classes as $class_name ) {
				if ( class_exists( $class_name ) ) {
					$instance = new $class_name();
					if ( $instance instanceof RLR_Geolocation_Provider ) {
						$providers[ $instance->get_id() ] = $instance;
					}
				}
			}
		}

		return $providers;
	}

	/**
	 * Look up the visitor's approximate geolocation, using cache and rate
	 * limiting. Returns a normalized array or WP_Error.
	 *
	 * @param string $ip IP address.
	 * @return array|WP_Error
	 */
	public static function locate_ip( $ip ) {
		if ( empty( $ip ) || ! RLR_Helpers::is_valid_public_ip( $ip ) ) {
			return new WP_Error( 'rlr_geo_invalid_ip', __( 'Could not determine a public IP address for this visitor.', 'restaurant-location-redirect' ) );
		}

		$settings = RLR_Settings::get_all();

		$cache_key = 'rlr_geo_' . RLR_Helpers::hash_ip( $ip );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		if ( self::is_rate_limited( $ip, (int) $settings['geo_rate_limit_per_hour'] ) ) {
			return new WP_Error( 'rlr_geo_rate_limited', __( 'Geolocation lookups are temporarily rate-limited for this address.', 'restaurant-location-redirect' ) );
		}

		$providers = self::get_available_providers();
		$provider_id = isset( $settings['geo_provider'] ) ? $settings['geo_provider'] : 'ip-api';

		if ( ! isset( $providers[ $provider_id ] ) ) {
			return new WP_Error( 'rlr_geo_no_provider', __( 'No geolocation provider is configured.', 'restaurant-location-redirect' ) );
		}

		/** @var RLR_Geolocation_Provider $provider */
		$provider = $providers[ $provider_id ];
		$api_key  = isset( $settings['geo_api_keys'][ $provider_id ] ) ? $settings['geo_api_keys'][ $provider_id ] : '';

		if ( $provider->requires_api_key() && empty( $api_key ) ) {
			return new WP_Error( 'rlr_geo_missing_key', __( 'The selected geolocation provider requires an API key.', 'restaurant-location-redirect' ) );
		}

		$result = $provider->locate( $ip, $api_key, (int) $settings['geo_request_timeout'] );

		self::bump_rate_limit( $ip );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result = wp_parse_args(
			$result,
			array(
				'country'      => '',
				'country_code' => '',
				'state'        => '',
				'city'         => '',
				'latitude'     => null,
				'longitude'    => null,
			)
		);

		$cache_hours = max( 0, (int) $settings['geo_cache_duration_hours'] );
		if ( $cache_hours > 0 ) {
			set_transient( $cache_key, $result, $cache_hours * HOUR_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * Whether the given IP has exceeded its hourly lookup allowance. This
	 * protects paid API keys from abuse/scraping.
	 *
	 * @param string $ip    IP address.
	 * @param int    $limit Max requests per rolling hour.
	 * @return bool
	 */
	private static function is_rate_limited( $ip, $limit ) {
		if ( $limit <= 0 ) {
			return false;
		}

		$key   = 'rlr_rl_' . RLR_Helpers::hash_ip( $ip );
		$count = (int) get_transient( $key );

		return $count >= $limit;
	}

	/**
	 * Increment the rate-limit counter for an IP.
	 *
	 * @param string $ip IP address.
	 */
	private static function bump_rate_limit( $ip ) {
		$key   = 'rlr_rl_' . RLR_Helpers::hash_ip( $ip );
		$count = (int) get_transient( $key );

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	}

	/**
	 * Clear the cached geolocation result for an IP (used in tests/debug).
	 *
	 * @param string $ip IP address.
	 */
	public static function clear_cache_for_ip( $ip ) {
		delete_transient( 'rlr_geo_' . RLR_Helpers::hash_ip( $ip ) );
	}
}

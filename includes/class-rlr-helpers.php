<?php
/**
 * Shared utility helpers.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static helper functions used across the plugin.
 */
class RLR_Helpers {

	/**
	 * Get the best-guess visitor IP address from the server request.
	 *
	 * Only used transiently for geolocation lookups; never persisted in
	 * full. See RLR_Geolocation::mask_ip() for the display-safe form.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );

			// X-Forwarded-For may contain a comma-separated chain; the first is the original client.
			if ( false !== strpos( $value, ',' ) ) {
				$parts = array_map( 'trim', explode( ',', $value ) );
				$value = $parts[0];
			}

			if ( self::is_valid_public_ip( $value ) ) {
				return $value;
			}
		}

		return '';
	}

	/**
	 * Determine whether a string is a syntactically valid, non-reserved IP.
	 *
	 * @param string $ip IP address.
	 * @return bool
	 */
	public static function is_valid_public_ip( $ip ) {
		if ( empty( $ip ) ) {
			return false;
		}

		return (bool) filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Mask an IP address for display in the admin debug panel, e.g. 203.0.113.xxx.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function mask_ip( $ip ) {
		if ( empty( $ip ) ) {
			return '';
		}

		if ( false !== strpos( $ip, '.' ) ) {
			$parts = explode( '.', $ip );
			if ( 4 === count( $parts ) ) {
				$parts[3] = 'xxx';
				return implode( '.', $parts );
			}
		}

		if ( false !== strpos( $ip, ':' ) ) {
			$parts = explode( ':', $ip );
			$keep  = array_slice( $parts, 0, max( 1, count( $parts ) - 4 ) );
			return implode( ':', $keep ) . ':xxxx';
		}

		return $ip;
	}

	/**
	 * A one-way hash of the IP, salted with an auth key, for rate limiting
	 * and cache keys without storing the raw address.
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	public static function hash_ip( $ip ) {
		$salt = defined( 'AUTH_SALT' ) && AUTH_SALT ? AUTH_SALT : 'rlr-fallback-salt';
		return hash( 'sha256', $salt . '|' . $ip );
	}

	/**
	 * Very small, dependency-free device category detector.
	 *
	 * @param string $user_agent Raw user agent string.
	 * @return string desktop|mobile|tablet
	 */
	public static function detect_device_category( $user_agent ) {
		$ua = strtolower( (string) $user_agent );

		if ( '' === $ua ) {
			return 'unknown';
		}

		if ( preg_match( '/ipad|tablet|kindle|playbook|silk/', $ua ) ) {
			return 'tablet';
		}

		if ( preg_match( '/mobile|iphone|ipod|android.*mobile|blackberry|windows phone/', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * Validate and normalize a URL intended to be an external order URL.
	 *
	 * @param string $url Raw URL.
	 * @return string|WP_Error Sanitized URL or WP_Error on failure.
	 */
	public static function validate_order_url( $url ) {
		$url = esc_url_raw( trim( (string) $url ) );

		if ( empty( $url ) ) {
			return new WP_Error( 'rlr_invalid_url', __( 'Order URL is required.', 'restaurant-location-redirect' ) );
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return new WP_Error( 'rlr_invalid_url', __( 'Order URL must start with http:// or https://.', 'restaurant-location-redirect' ) );
		}

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'rlr_invalid_url', __( 'Order URL is not a valid URL.', 'restaurant-location-redirect' ) );
		}

		return $url;
	}

	/**
	 * Generate a URL-safe slug for a location, ensuring uniqueness against
	 * existing rows via the supplied callback.
	 *
	 * @param string   $name         Location name.
	 * @param callable $exists_check function( string $slug ) : bool.
	 * @return string
	 */
	public static function unique_slug( $name, $exists_check ) {
		$base = sanitize_title( $name );
		if ( '' === $base ) {
			$base = 'location';
		}

		$slug  = $base;
		$index = 2;

		while ( call_user_func( $exists_check, $slug ) ) {
			$slug = $base . '-' . $index;
			++$index;
		}

		return $slug;
	}

	/**
	 * Simple referrer bucketing without storing the full referrer URL.
	 *
	 * @param string $referrer Raw referrer URL.
	 * @param string $site_host Current site host.
	 * @return string
	 */
	public static function categorize_referrer( $referrer, $site_host ) {
		if ( empty( $referrer ) ) {
			return 'direct';
		}

		$host = wp_parse_url( $referrer, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return 'direct';
		}

		if ( $host === $site_host ) {
			return 'internal';
		}

		$search_engines = array( 'google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.' );
		foreach ( $search_engines as $needle ) {
			if ( false !== strpos( $host, $needle ) ) {
				return 'search';
			}
		}

		$social = array( 'facebook.', 'instagram.', 'twitter.', 'x.com', 'tiktok.', 'linkedin.', 'pinterest.' );
		foreach ( $social as $needle ) {
			if ( false !== strpos( $host, $needle ) ) {
				return 'social';
			}
		}

		return 'referral';
	}
}

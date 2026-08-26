<?php
/**
 * Geolocation provider: ipinfo.io (requires an API token for production
 * use). See https://ipinfo.io/developers
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Provider_IPInfo implements RLR_Geolocation_Provider {

	public function get_id() {
		return 'ipinfo';
	}

	public function get_label() {
		return __( 'ipinfo.io (API key required)', 'restaurant-location-redirect' );
	}

	public function requires_api_key() {
		return true;
	}

	/**
	 * @param string $ip
	 * @param string $api_key
	 * @param int    $timeout
	 * @return array|WP_Error
	 */
	public function locate( $ip, $api_key, $timeout ) {
		if ( empty( $api_key ) ) {
			return new WP_Error( 'rlr_geo_missing_key', __( 'ipinfo.io requires an API key. Add one in Geolocation settings.', 'restaurant-location-redirect' ) );
		}

		$url = 'https://ipinfo.io/' . rawurlencode( $ip ) . '/json';

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => true,
				'headers'   => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'rlr_geo_http_error', sprintf( 'ipinfo.io returned HTTP %d', $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) || isset( $body['error'] ) ) {
			return new WP_Error( 'rlr_geo_bad_response', 'ipinfo.io returned an invalid response.' );
		}

		$lat = null;
		$lon = null;
		if ( ! empty( $body['loc'] ) && false !== strpos( $body['loc'], ',' ) ) {
			list( $lat_raw, $lon_raw ) = array_map( 'trim', explode( ',', $body['loc'], 2 ) );
			if ( is_numeric( $lat_raw ) && is_numeric( $lon_raw ) ) {
				$lat = (float) $lat_raw;
				$lon = (float) $lon_raw;
			}
		}

		return array(
			'country'      => isset( $body['country'] ) ? sanitize_text_field( $body['country'] ) : '',
			'country_code' => isset( $body['country'] ) ? sanitize_text_field( $body['country'] ) : '',
			'state'        => isset( $body['region'] ) ? sanitize_text_field( $body['region'] ) : '',
			'city'         => isset( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '',
			'latitude'     => $lat,
			'longitude'    => $lon,
		);
	}
}

<?php
/**
 * Geolocation provider: ip-api.com (free tier, no API key required for
 * non-commercial use over HTTP). See https://ip-api.com/docs
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Provider_IPAPI implements RLR_Geolocation_Provider {

	public function get_id() {
		return 'ip-api';
	}

	public function get_label() {
		return __( 'ip-api.com (free tier)', 'restaurant-location-redirect' );
	}

	public function requires_api_key() {
		return false;
	}

	/**
	 * @param string $ip
	 * @param string $api_key
	 * @param int    $timeout
	 * @return array|WP_Error
	 */
	public function locate( $ip, $api_key, $timeout ) {
		$fields = 'status,message,country,countryCode,regionName,city,lat,lon';
		$url    = add_query_arg(
			array( 'fields' => $fields ),
			'http://ip-api.com/json/' . rawurlencode( $ip )
		);

		if ( ! empty( $api_key ) ) {
			// Pro/paid key supports HTTPS endpoint.
			$url = add_query_arg(
				array(
					'fields' => $fields,
					'key'    => $api_key,
				),
				'https://pro.ip-api.com/json/' . rawurlencode( $ip )
			);
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => $timeout,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error( 'rlr_geo_http_error', sprintf( 'ip-api.com returned HTTP %d', $code ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $body ) ) {
			return new WP_Error( 'rlr_geo_bad_response', 'ip-api.com returned an invalid response.' );
		}

		if ( isset( $body['status'] ) && 'fail' === $body['status'] ) {
			return new WP_Error( 'rlr_geo_lookup_failed', isset( $body['message'] ) ? $body['message'] : 'Lookup failed.' );
		}

		return array(
			'country'      => isset( $body['country'] ) ? sanitize_text_field( $body['country'] ) : '',
			'country_code' => isset( $body['countryCode'] ) ? sanitize_text_field( $body['countryCode'] ) : '',
			'state'        => isset( $body['regionName'] ) ? sanitize_text_field( $body['regionName'] ) : '',
			'city'         => isset( $body['city'] ) ? sanitize_text_field( $body['city'] ) : '',
			'latitude'     => isset( $body['lat'] ) ? (float) $body['lat'] : null,
			'longitude'    => isset( $body['lon'] ) ? (float) $body['lon'] : null,
		);
	}
}

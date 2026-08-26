<?php
/**
 * REST API endpoints (namespace rlr/v1).
 *
 * Routes:
 *   GET  /rlr/v1/locations  Public list of active locations (cacheable).
 *   GET  /rlr/v1/detect     Visitor-specific geolocation + match (never cache).
 *   POST /rlr/v1/track      Analytics event ingestion (nonce required).
 *   POST /rlr/v1/simulate   Admin-only: run the matcher against arbitrary
 *                           city/state/country input, with no external API
 *                           call — for testing matching logic from anywhere.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_REST {

	const NAMESPACE_V1 = 'rlr/v1';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::NAMESPACE_V1,
			'/locations',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_locations' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/detect',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'detect_location' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/track',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'track_event' ),
				'permission_callback' => array( $this, 'verify_public_nonce' ),
				'args'                => array(
					'event' => array(
						'required' => true,
						'type'     => 'string',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/simulate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'simulate_match' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Verify the wp_rest nonce for state-recording (but non-privileged)
	 * requests from anonymous or logged-in visitors alike.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function verify_public_nonce( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * GET /locations — public, cacheable list of active locations.
	 *
	 * @return WP_REST_Response
	 */
	public function get_locations() {
		$rows = RLR_Location_Manager::get_active();
		$data = array_map( array( 'RLR_Location_Manager', 'to_public_array' ), $rows );

		$response = new WP_REST_Response(
			array(
				'success'   => true,
				'locations' => $data,
			)
		);

		// Safe to cache briefly at the edge/CDN: identical for every visitor.
		$response->header( 'Cache-Control', 'public, max-age=300' );

		return $response;
	}

	/**
	 * GET /detect — visitor-specific geolocation + match. Must never be
	 * cached, since the result differs per visitor IP.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function detect_location( WP_REST_Request $request ) {
		$settings = RLR_Settings::get_all();

		$response_body = array(
			'success'         => false,
			'matched'         => false,
			'location'        => null,
			'confidence'      => 'none',
			'match_method'    => 'none',
			'reason'          => '',
		);

		if ( empty( $settings['enabled'] ) || empty( $settings['enable_geolocation'] ) ) {
			$response_body['reason'] = 'geolocation_disabled';
			return $this->no_cache_response( $response_body );
		}

		$ip = RLR_Helpers::get_client_ip();

		$geo = RLR_Geolocation::locate_ip( $ip );

		$debug = array();
		$is_debug_viewer = current_user_can( 'manage_options' ) && ! empty( $settings['debug_mode'] );

		if ( is_wp_error( $geo ) ) {
			$response_body['reason'] = $geo->get_error_code();

			if ( $is_debug_viewer ) {
				$debug['ip']    = RLR_Helpers::mask_ip( $ip );
				$debug['error'] = $geo->get_error_message();
				$response_body['debug'] = $debug;
			}

			return $this->no_cache_response( $response_body );
		}

		$active_locations = RLR_Location_Manager::get_active();

		$match = RLR_Matcher::match(
			$geo,
			$active_locations,
			array(
				'proximity_enabled'   => ! empty( $settings['proximity_enabled'] ),
				'proximity_radius_km' => (float) $settings['proximity_radius_km'],
			)
		);

		$meets_threshold = RLR_Matcher::meets_threshold( $match['confidence'], $settings['confidence_threshold'] );

		$response_body['success']      = true;
		$response_body['confidence']   = $match['confidence'];
		$response_body['match_method'] = $match['method'];
		$response_body['reason']       = $match['reason'];

		if ( $match['location'] && $meets_threshold ) {
			$response_body['matched']  = true;
			$response_body['location'] = RLR_Location_Manager::to_public_array( $match['location'] );
		}

		if ( $is_debug_viewer ) {
			$debug['ip']              = RLR_Helpers::mask_ip( $ip );
			$debug['detected_country'] = $geo['country'];
			$debug['detected_state']   = $geo['state'];
			$debug['detected_city']    = $geo['city'];
			$debug['candidates']       = $match['candidates'];
			$debug['meets_threshold']  = $meets_threshold;
			$debug['threshold']        = $settings['confidence_threshold'];
			$response_body['debug']    = $debug;
		}

		return $this->no_cache_response( $response_body );
	}

	/**
	 * POST /simulate — admin-only diagnostic tool. Runs the same matching
	 * hierarchy as /detect against admin-supplied city/state/country/lat/lng
	 * values instead of a real IP lookup, so the matching logic (which
	 * locations win at which confidence) can be verified from anywhere in
	 * the world without a VPN and without spending API quota.
	 *
	 * Requires `manage_options`; never reachable by ordinary visitors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function simulate_match( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$detected = array(
			'city'         => isset( $params['city'] ) ? sanitize_text_field( $params['city'] ) : '',
			'state'        => isset( $params['state'] ) ? sanitize_text_field( $params['state'] ) : '',
			'country'      => isset( $params['country'] ) ? sanitize_text_field( $params['country'] ) : '',
			'country_code' => isset( $params['country_code'] ) ? sanitize_text_field( $params['country_code'] ) : '',
			'latitude'     => ( isset( $params['latitude'] ) && is_numeric( $params['latitude'] ) ) ? (float) $params['latitude'] : null,
			'longitude'    => ( isset( $params['longitude'] ) && is_numeric( $params['longitude'] ) ) ? (float) $params['longitude'] : null,
		);

		$settings         = RLR_Settings::get_all();
		$active_locations = RLR_Location_Manager::get_active();

		$match = RLR_Matcher::match(
			$detected,
			$active_locations,
			array(
				'proximity_enabled'   => ! empty( $settings['proximity_enabled'] ),
				'proximity_radius_km' => (float) $settings['proximity_radius_km'],
			)
		);

		$meets_threshold = RLR_Matcher::meets_threshold( $match['confidence'], $settings['confidence_threshold'] );

		return new WP_REST_Response(
			array(
				'success'      => true,
				'input'        => $detected,
				'matched'      => (bool) ( $match['location'] && $meets_threshold ),
				'location'     => $match['location'] ? RLR_Location_Manager::to_public_array( $match['location'] ) : null,
				'confidence'   => $match['confidence'],
				'match_method' => $match['method'],
				'candidates'   => $match['candidates'],
				'reason'       => $match['reason'],
				'threshold'    => $settings['confidence_threshold'],
				'meets_threshold' => $meets_threshold,
			)
		);
	}

	/**
	 * POST /track — record a privacy-minimized analytics event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function track_event( WP_REST_Request $request ) {
		if ( ! RLR_Analytics::is_enabled() ) {
			return new WP_REST_Response( array( 'success' => false, 'reason' => 'analytics_disabled' ) );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = $request->get_params();
		}

		$location_id = null;
		if ( ! empty( $params['location_id'] ) ) {
			$candidate = absint( $params['location_id'] );
			if ( RLR_Location_Manager::get( $candidate ) ) {
				$location_id = $candidate;
			}
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$recorded = RLR_Analytics::record(
			array(
				'event_type'        => isset( $params['event'] ) ? $params['event'] : '',
				'location_id'       => $location_id,
				'match_method'      => isset( $params['match_method'] ) ? $params['match_method'] : null,
				'confidence'        => isset( $params['confidence'] ) ? $params['confidence'] : null,
				'session_hash'      => isset( $params['session_id'] ) ? $params['session_id'] : null,
				'device_category'   => RLR_Helpers::detect_device_category( $user_agent ),
				'referrer_category' => isset( $params['referrer_category'] ) ? sanitize_key( $params['referrer_category'] ) : 'direct',
			)
		);

		return new WP_REST_Response( array( 'success' => (bool) $recorded ) );
	}

	/**
	 * Wrap a body array in a WP_REST_Response with strict no-cache headers,
	 * so page-cache/CDN layers never store one visitor's detected location
	 * and serve it to another visitor.
	 *
	 * @param array $body Response body.
	 * @return WP_REST_Response
	 */
	private function no_cache_response( array $body ) {
		$response = new WP_REST_Response( $body );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}
}

<?php
/**
 * Tests for the REST API endpoints.
 *
 * @package Restaurant_Location_Redirect
 */

class Test_RLR_REST extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	public function test_locations_endpoint_returns_only_active() {
		RLR_Location_Manager::create(
			array( 'name' => 'Arizona', 'city' => 'Phoenix', 'country' => 'United States', 'order_url' => 'https://example.com/arizona-order' )
		);
		RLR_Location_Manager::create(
			array( 'name' => 'Hidden', 'city' => 'Nowhere', 'country' => 'United States', 'order_url' => 'https://example.com/hidden', 'status' => 'inactive' )
		);

		$request  = new WP_REST_Request( 'GET', '/rlr/v1/locations' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertCount( 1, $data['locations'] );
		$this->assertSame( 'Arizona', $data['locations'][0]['name'] );
	}

	public function test_locations_endpoint_never_exposes_api_keys() {
		$settings = RLR_Settings::get_all();
		$settings['geo_api_keys']['ipinfo'] = 'super-secret-key';
		RLR_Settings::update_all( $settings );

		$request  = new WP_REST_Request( 'GET', '/rlr/v1/locations' );
		$response = rest_get_server()->dispatch( $request );
		$body     = wp_json_encode( $response->get_data() );

		$this->assertStringNotContainsString( 'super-secret-key', $body );
	}

	public function test_detect_endpoint_has_no_cache_headers() {
		$request  = new WP_REST_Request( 'GET', '/rlr/v1/detect' );
		$response = rest_get_server()->dispatch( $request );
		$headers  = $response->get_headers();

		$this->assertArrayHasKey( 'Cache-Control', $headers );
		$this->assertStringContainsString( 'no-store', $headers['Cache-Control'] );
	}

	public function test_detect_endpoint_does_not_leak_debug_to_non_admin() {
		$request  = new WP_REST_Request( 'GET', '/rlr/v1/detect' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'debug', $data );
	}

	public function test_detect_endpoint_exposes_debug_to_admin_when_enabled() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$settings = RLR_Settings::get_all();
		$settings['debug_mode'] = true;
		RLR_Settings::update_all( $settings );

		$request  = new WP_REST_Request( 'GET', '/rlr/v1/detect' );
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'debug', $data );

		wp_set_current_user( 0 );
	}

	public function test_track_endpoint_requires_valid_nonce() {
		$settings = RLR_Settings::get_all();
		$settings['analytics_mode'] = 'internal';
		RLR_Settings::update_all( $settings );

		$request = new WP_REST_Request( 'POST', '/rlr/v1/track' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'event' => 'order_click' ) ) );

		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_track_endpoint_accepts_valid_nonce_and_records_event() {
		$settings = RLR_Settings::get_all();
		$settings['analytics_mode'] = 'internal';
		RLR_Settings::update_all( $settings );

		$nonce = wp_create_nonce( 'wp_rest' );

		$request = new WP_REST_Request( 'POST', '/rlr/v1/track' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', $nonce );
		$request->set_body( wp_json_encode( array( 'event' => 'order_click' ) ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
	}

	public function test_track_endpoint_noop_when_analytics_disabled() {
		$settings = RLR_Settings::get_all();
		$settings['analytics_mode'] = 'disabled';
		RLR_Settings::update_all( $settings );

		$nonce = wp_create_nonce( 'wp_rest' );

		$request = new WP_REST_Request( 'POST', '/rlr/v1/track' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_header( 'X-WP-Nonce', $nonce );
		$request->set_body( wp_json_encode( array( 'event' => 'order_click' ) ) );

		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();

		$this->assertFalse( $data['success'] );
	}
}

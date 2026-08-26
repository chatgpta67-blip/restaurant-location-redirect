<?php
/**
 * Tests for RLR_Analytics.
 *
 * @package Restaurant_Location_Redirect
 */

class Test_RLR_Analytics extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . RLR_Analytics::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	private function set_mode( $mode ) {
		$settings = RLR_Settings::get_all();
		$settings['analytics_mode'] = $mode;
		RLR_Settings::update_all( $settings );
	}

	public function test_record_is_noop_when_disabled() {
		$this->set_mode( 'disabled' );

		$result = RLR_Analytics::record( array( 'event_type' => 'order_click' ) );

		$this->assertFalse( $result );

		global $wpdb;
		$count = $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RLR_Analytics::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( '0', $count );
	}

	public function test_record_stores_event_when_enabled() {
		$this->set_mode( 'internal' );

		$result = RLR_Analytics::record(
			array(
				'event_type'      => 'order_click',
				'location_id'     => 1,
				'match_method'    => 'city',
				'confidence'      => 'high',
				'device_category' => 'mobile',
			)
		);

		$this->assertTrue( $result );
	}

	public function test_record_rejects_unknown_event_type() {
		$this->set_mode( 'internal' );

		$result = RLR_Analytics::record( array( 'event_type' => 'totally_not_a_real_event' ) );

		$this->assertFalse( $result );
	}

	public function test_record_never_stores_raw_ip_field() {
		$this->set_mode( 'internal' );

		RLR_Analytics::record(
			array(
				'event_type' => 'order_click',
				// Even if a caller mistakenly passes one, there is no column for it.
				'ip'         => '203.0.113.5',
			)
		);

		global $wpdb;
		$columns = $wpdb->get_col( 'DESCRIBE ' . RLR_Analytics::table(), 0 ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertNotContains( 'ip', $columns );
		$this->assertNotContains( 'ip_address', $columns );
	}

	public function test_retention_cleanup_removes_old_events() {
		$this->set_mode( 'internal' );
		global $wpdb;

		$wpdb->insert(
			RLR_Analytics::table(),
			array(
				'event_type' => 'order_click',
				'created_at' => gmdate( 'Y-m-d H:i:s', time() - ( 200 * DAY_IN_SECONDS ) ),
			)
		);
		$wpdb->insert(
			RLR_Analytics::table(),
			array(
				'event_type' => 'order_click',
				'created_at' => current_time( 'mysql', true ),
			)
		);

		$settings = RLR_Settings::get_all();
		$settings['analytics_retention_days'] = 90;
		RLR_Settings::update_all( $settings );

		RLR_Analytics::run_retention_cleanup();

		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . RLR_Analytics::table() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$this->assertSame( 1, $count );
	}

	public function test_dashboard_data_aggregates_totals() {
		$this->set_mode( 'internal' );

		$loc_id = RLR_Location_Manager::create(
			array( 'name' => 'Arizona', 'city' => 'Phoenix', 'country' => 'United States', 'order_url' => 'https://example.com/a' )
		);

		RLR_Analytics::record( array( 'event_type' => 'auto_match', 'location_id' => $loc_id ) );
		RLR_Analytics::record( array( 'event_type' => 'order_click', 'location_id' => $loc_id ) );
		RLR_Analytics::record( array( 'event_type' => 'order_click', 'location_id' => $loc_id ) );

		$today = current_time( 'Y-m-d' );
		$data  = RLR_Analytics::get_dashboard_data( $today, $today );

		$this->assertSame( 1, $data['totals']['auto_match'] );
		$this->assertSame( 2, $data['totals']['order_click'] );
		$this->assertSame( 200.0, $data['conversion_rate'] );
	}
}

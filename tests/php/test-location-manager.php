<?php
/**
 * Tests for RLR_Location_Manager CRUD + validation.
 *
 * @package Restaurant_Location_Redirect
 */

class Test_RLR_Location_Manager extends WP_UnitTestCase {

	public function test_create_requires_city_and_country() {
		$result = RLR_Location_Manager::create(
			array(
				'name'      => 'Test Location',
				'city'      => '',
				'country'   => '',
				'order_url' => 'https://example.com/order',
			)
		);

		$this->assertWPError( $result );
	}

	public function test_create_rejects_invalid_url() {
		$result = RLR_Location_Manager::create(
			array(
				'name'      => 'Arizona',
				'city'      => 'Phoenix',
				'state'     => 'Arizona',
				'country'   => 'United States',
				'order_url' => 'javascript:alert(1)',
			)
		);

		$this->assertWPError( $result );
	}

	public function test_create_and_retrieve_location() {
		$id = RLR_Location_Manager::create(
			array(
				'name'      => 'Arizona',
				'city'      => 'Phoenix',
				'state'     => 'Arizona',
				'country'   => 'United States',
				'country_code' => 'US',
				'order_url' => 'https://example.com/arizona-order',
			)
		);

		$this->assertIsInt( $id );
		$this->assertGreaterThan( 0, $id );

		$location = RLR_Location_Manager::get( $id );
		$this->assertSame( 'Arizona', $location['name'] );
		$this->assertSame( 'arizona', $location['slug'] );
		$this->assertSame( 'active', $location['status'] );
	}

	public function test_duplicate_names_get_unique_slugs() {
		$id1 = RLR_Location_Manager::create(
			array( 'name' => 'Downtown', 'city' => 'Phoenix', 'country' => 'United States', 'order_url' => 'https://example.com/a' )
		);
		$id2 = RLR_Location_Manager::create(
			array( 'name' => 'Downtown', 'city' => 'Manchester', 'country' => 'United Kingdom', 'order_url' => 'https://example.com/b' )
		);

		$loc1 = RLR_Location_Manager::get( $id1 );
		$loc2 = RLR_Location_Manager::get( $id2 );

		$this->assertNotSame( $loc1['slug'], $loc2['slug'] );
	}

	public function test_toggle_status() {
		$id = RLR_Location_Manager::create(
			array( 'name' => 'Flamingo', 'city' => 'Las Vegas', 'state' => 'Nevada', 'country' => 'United States', 'order_url' => 'https://example.com/flamingo' )
		);

		$this->assertTrue( RLR_Location_Manager::toggle_status( $id ) );
		$this->assertSame( 'inactive', RLR_Location_Manager::get( $id )['status'] );

		RLR_Location_Manager::toggle_status( $id );
		$this->assertSame( 'active', RLR_Location_Manager::get( $id )['status'] );
	}

	public function test_get_active_excludes_inactive() {
		$active_id   = RLR_Location_Manager::create(
			array( 'name' => 'Active One', 'city' => 'City A', 'country' => 'United States', 'order_url' => 'https://example.com/a' )
		);
		$inactive_id = RLR_Location_Manager::create(
			array( 'name' => 'Inactive One', 'city' => 'City B', 'country' => 'United States', 'order_url' => 'https://example.com/b', 'status' => 'inactive' )
		);

		$active_ids = wp_list_pluck( RLR_Location_Manager::get_active(), 'id' );

		$this->assertContains( $active_id, $active_ids );
		$this->assertNotContains( $inactive_id, $active_ids );
	}

	public function test_delete_location() {
		$id = RLR_Location_Manager::create(
			array( 'name' => 'Temp', 'city' => 'Temp City', 'country' => 'United States', 'order_url' => 'https://example.com/temp' )
		);

		$this->assertTrue( RLR_Location_Manager::delete( $id ) );
		$this->assertNull( RLR_Location_Manager::get( $id ) );
	}

	public function test_to_public_array_excludes_internal_fields() {
		$id  = RLR_Location_Manager::create(
			array( 'name' => 'Arizona', 'city' => 'Phoenix', 'state' => 'Arizona', 'country' => 'United States', 'order_url' => 'https://example.com/arizona-order' )
		);
		$row = RLR_Location_Manager::get( $id );
		$public = RLR_Location_Manager::to_public_array( $row );

		$this->assertArrayNotHasKey( 'created_at', $public );
		$this->assertArrayNotHasKey( 'sort_order', $public );
		$this->assertSame( 'https://example.com/arizona-order', $public['orderUrl'] );
	}

	/**
	 * Regression: the active-locations list is rendered directly into page
	 * HTML (for cache-safety -- identical for every visitor), which means a
	 * page cached before a location changes keeps showing the old list
	 * until purged. This locks in that every successful write purges
	 * LiteSpeed Cache (a harmless no-op when it isn't installed), so a
	 * newly-added/edited location shows up without a manual cache purge.
	 */
	public function test_successful_writes_purge_litespeed_cache() {
		$purge_count = 0;
		$count_purge = function () use ( &$purge_count ) {
			++$purge_count;
		};
		add_action( 'litespeed_purge_all', $count_purge );

		$id = RLR_Location_Manager::create(
			array( 'name' => 'Chennai', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'country' => 'India', 'order_url' => 'https://example.com/chennai' )
		);
		$this->assertSame( 1, $purge_count );

		RLR_Location_Manager::update( $id, array( 'name' => 'Chennai HQ', 'city' => 'Chennai', 'country' => 'India', 'order_url' => 'https://example.com/chennai' ) );
		$this->assertSame( 2, $purge_count );

		RLR_Location_Manager::toggle_status( $id );
		$this->assertSame( 3, $purge_count );

		RLR_Location_Manager::delete( $id );
		$this->assertSame( 4, $purge_count );

		remove_action( 'litespeed_purge_all', $count_purge );
	}

	public function test_failed_create_does_not_purge_cache() {
		$purge_count = 0;
		$count_purge = function () use ( &$purge_count ) {
			++$purge_count;
		};
		add_action( 'litespeed_purge_all', $count_purge );

		RLR_Location_Manager::create( array( 'name' => 'Missing Fields', 'city' => '', 'country' => '', 'order_url' => '' ) );

		$this->assertSame( 0, $purge_count );

		remove_action( 'litespeed_purge_all', $count_purge );
	}
}

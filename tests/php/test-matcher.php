<?php
/**
 * Tests for RLR_Matcher — the confidence-scored matching hierarchy.
 *
 * @package Restaurant_Location_Redirect
 */

class Test_RLR_Matcher extends WP_UnitTestCase {

	private $locations;

	public function set_up() {
		parent::set_up();

		$this->locations = array(
			array(
				'id' => 1, 'name' => 'Arizona', 'city' => 'Phoenix', 'state' => 'Arizona',
				'country' => 'United States', 'country_code' => 'US', 'order_url' => 'https://example.com/arizona-order',
				'latitude' => 33.4484, 'longitude' => -112.0740,
			),
			array(
				'id' => 2, 'name' => 'Manchester', 'city' => 'Manchester', 'state' => 'England',
				'country' => 'United Kingdom', 'country_code' => 'GB', 'order_url' => 'https://example.com/manchester-order',
				'latitude' => 53.4808, 'longitude' => -2.2426,
			),
			array(
				'id' => 3, 'name' => 'Flamingo', 'city' => 'Las Vegas', 'state' => 'Nevada',
				'country' => 'United States', 'country_code' => 'US', 'order_url' => 'https://example.com/flamingo-order',
				'latitude' => 36.1147, 'longitude' => -115.1728,
			),
		);
	}

	/** Test 1: Arizona — full city+state+country match => high confidence. */
	public function test_city_level_match_is_high_confidence() {
		$result = RLR_Matcher::match(
			array( 'city' => 'Phoenix', 'state' => 'Arizona', 'country' => 'United States', 'country_code' => 'US' ),
			$this->locations
		);

		$this->assertSame( 1, $result['location']['id'] );
		$this->assertSame( 'high', $result['confidence'] );
		$this->assertSame( 'city', $result['method'] );
	}

	/** Test 2: Manchester, using country name alias "UK". */
	public function test_manchester_match_with_country_alias() {
		$result = RLR_Matcher::match(
			array( 'city' => 'Manchester', 'state' => 'England', 'country' => 'UK' ),
			$this->locations
		);

		$this->assertSame( 2, $result['location']['id'] );
		$this->assertSame( 'high', $result['confidence'] );
	}

	public function test_state_level_match_when_city_unknown() {
		$result = RLR_Matcher::match(
			array( 'city' => '', 'state' => 'Nevada', 'country' => 'United States', 'country_code' => 'US' ),
			$this->locations
		);

		$this->assertSame( 3, $result['location']['id'] );
		$this->assertSame( 'medium', $result['confidence'] );
		$this->assertSame( 'state', $result['method'] );
	}

	public function test_country_only_match_is_low_confidence_when_ambiguous() {
		// Two US locations exist (Arizona, Flamingo) — country alone is ambiguous.
		$result = RLR_Matcher::match(
			array( 'city' => '', 'state' => '', 'country' => 'United States', 'country_code' => 'US' ),
			$this->locations
		);

		$this->assertNull( $result['location'] );
		$this->assertSame( 'low', $result['confidence'] );
		$this->assertSame( 'ambiguous_country', $result['reason'] );
	}

	/** Test 6: Unknown location — no matching country at all. */
	public function test_no_match_for_unrelated_country() {
		$result = RLR_Matcher::match(
			array( 'city' => 'Tokyo', 'state' => '', 'country' => 'Japan', 'country_code' => 'JP' ),
			$this->locations
		);

		$this->assertNull( $result['location'] );
		$this->assertSame( 'none', $result['confidence'] );
	}

	public function test_missing_geo_data_is_handled_gracefully() {
		$result = RLR_Matcher::match( array(), $this->locations );

		$this->assertNull( $result['location'] );
		$this->assertSame( 'none', $result['confidence'] );
		$this->assertSame( 'no_geo_data', $result['reason'] );
	}

	public function test_conflicting_city_without_state_falls_back_via_proximity() {
		// City doesn't match anything, but coordinates are close to Flamingo (Las Vegas).
		$result = RLR_Matcher::match(
			array(
				'city' => 'Henderson', 'state' => '', 'country' => 'United States', 'country_code' => 'US',
				'latitude' => 36.05, 'longitude' => -115.05,
			),
			$this->locations,
			array( 'proximity_enabled' => true, 'proximity_radius_km' => 80 )
		);

		$this->assertSame( 3, $result['location']['id'] );
		$this->assertSame( 'proximity', $result['method'] );
	}

	public function test_no_locations_configured() {
		$result = RLR_Matcher::match( array( 'city' => 'Phoenix', 'country' => 'US' ), array() );

		$this->assertNull( $result['location'] );
		$this->assertSame( 'none', $result['confidence'] );
		$this->assertSame( 'no_locations_configured', $result['reason'] );
	}

	public function test_confidence_threshold_ordering() {
		$this->assertTrue( RLR_Matcher::meets_threshold( 'high', 'medium' ) );
		$this->assertTrue( RLR_Matcher::meets_threshold( 'medium', 'medium' ) );
		$this->assertFalse( RLR_Matcher::meets_threshold( 'low', 'medium' ) );
		$this->assertFalse( RLR_Matcher::meets_threshold( 'none', 'low' ) );
	}
}

<?php
/**
 * Tests for RLR_Settings sanitization.
 *
 * @package Restaurant_Location_Redirect
 */

class Test_RLR_Settings extends WP_UnitTestCase {

	public function test_defaults_are_safe() {
		$defaults = RLR_Settings::defaults();

		$this->assertTrue( $defaults['enabled'] );
		$this->assertSame( 'disabled', $defaults['analytics_mode'] );
		$this->assertSame( 30, $defaults['storage_duration_days'] );
	}

	public function test_sanitize_clamps_out_of_range_numbers() {
		$out = RLR_Settings::sanitize(
			array(
				'storage_duration_days' => -5,
				'geo_cache_duration_hours' => 99999,
				'proximity_radius_km' => -10,
			)
		);

		$this->assertSame( 1, $out['storage_duration_days'] );
		$this->assertSame( 720, $out['geo_cache_duration_hours'] );
		$this->assertGreaterThanOrEqual( 1, $out['proximity_radius_km'] );
	}

	public function test_sanitize_rejects_unknown_enum_values() {
		$before = RLR_Settings::get_all();

		$out = RLR_Settings::sanitize( array( 'confidence_threshold' => 'super-high' ) );

		$this->assertSame( $before['confidence_threshold'], $out['confidence_threshold'] );
	}

	public function test_sanitize_strips_unsafe_css_selector() {
		$out = RLR_Settings::sanitize( array( 'button_selector' => '.order-now"><script>alert(1)</script>' ) );

		$this->assertStringNotContainsString( '<script>', $out['button_selector'] );
	}

	public function test_sanitize_accepts_valid_selector() {
		$out = RLR_Settings::sanitize( array( 'button_selector' => '.order-now, a[data-role="order-now"]' ) );

		$this->assertSame( '.order-now, a[data-role="order-now"]', $out['button_selector'] );
	}

	public function test_checkbox_fields_default_false_when_absent() {
		$out = RLR_Settings::sanitize( array() );

		$this->assertFalse( $out['enabled'] );
		$this->assertFalse( $out['debug_mode'] );
	}
}

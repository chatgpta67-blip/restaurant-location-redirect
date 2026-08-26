<?php
/**
 * PHPUnit bootstrap for the WordPress core test suite.
 *
 * Expects the standard WP_TESTS_DIR / wp-phpunit setup. Easiest path:
 *
 *   composer require --dev wp-phpunit/wp-phpunit yoast/phpunit-polyfills
 *   WP_TESTS_DIR=$(pwd)/vendor/wp-phpunit/wp-phpunit phpunit -c phpunit.xml.dist
 *
 * Or run against `wp-env` — see docs/README.md "Testing" section for both
 * options.
 *
 * @package Restaurant_Location_Redirect
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

require_once $_tests_dir . '/includes/functions.php';

/**
 * Load the plugin under test.
 */
function _rlr_manually_load_plugin() {
	require dirname( __DIR__, 2 ) . '/restaurant-location-redirect.php';
}
tests_add_filter( 'muplugins_loaded', '_rlr_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';

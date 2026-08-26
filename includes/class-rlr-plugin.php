<?php
/**
 * Main orchestrator: wires up admin, public, REST, and analytics hooks.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Plugin {

	/**
	 * @var RLR_Admin
	 */
	private $admin;

	/**
	 * @var RLR_Public
	 */
	private $public;

	/**
	 * @var RLR_REST
	 */
	private $rest;

	public function __construct() {
		$this->admin  = new RLR_Admin();
		$this->public = new RLR_Public();
		$this->rest   = new RLR_REST();
	}

	/**
	 * Register all WordPress hooks.
	 */
	public function run() {
		load_plugin_textdomain( 'restaurant-location-redirect', false, dirname( RLR_PLUGIN_BASENAME ) . '/languages' );

		RLR_Analytics::init();

		add_action( 'rest_api_init', array( $this->rest, 'register_routes' ) );

		if ( is_admin() ) {
			$this->admin->init();
		}

		$this->public->init();
	}
}

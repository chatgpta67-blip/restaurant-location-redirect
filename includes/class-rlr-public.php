<?php
/**
 * Frontend integration: asset enqueueing, localized config, the location
 * modal markup, and convenience shortcodes.
 *
 * Deliberately renders zero visitor-specific HTML. The active-locations
 * list is identical for every visitor so it is safe to print into the
 * (possibly cached) page; the actual location DECISION always happens in
 * the browser via JS + the /detect REST endpoint, which is never cached.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Public {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_modal' ) );
		add_shortcode( 'rlr_change_location', array( $this, 'shortcode_change_location' ) );
		add_shortcode( 'rlr_location_switcher', array( $this, 'shortcode_location_switcher' ) );
		add_shortcode( 'rlr_order_button', array( $this, 'shortcode_order_button' ) );
	}

	/**
	 * Whether the plugin's frontend behavior should run at all.
	 *
	 * @return bool
	 */
	private function is_active() {
		return (bool) RLR_Settings::get( 'enabled', true );
	}

	/**
	 * Enqueue public CSS/JS and localize configuration.
	 */
	public function enqueue_assets() {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_style( 'rlr-public', RLR_PLUGIN_URL . 'public/css/rlr-public.css', array(), RLR_VERSION );

		wp_enqueue_script( 'rlr-public', RLR_PLUGIN_URL . 'public/js/rlr-public.js', array(), RLR_VERSION, true );

		$settings = RLR_Settings::get_all();

		$locations = array_map( array( 'RLR_Location_Manager', 'to_public_array' ), RLR_Location_Manager::get_active() );

		$config = array(
			'restUrl'       => esc_url_raw( rest_url( RLR_REST::NAMESPACE_V1 ) ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'siteHost'      => wp_parse_url( home_url(), PHP_URL_HOST ),
			'locations'     => $locations,
			'settings'      => array(
				'buttonSelector'         => $settings['button_selector'],
				'changeLocationSelector' => $settings['change_location_selector'],
				'storageDurationDays'    => (int) $settings['storage_duration_days'],
				'storageMethod'          => $settings['storage_method'],
				'enableGeolocation'      => (bool) $settings['enable_geolocation'],
				'enablePopup'            => (bool) $settings['enable_popup'],
				'debugMode'              => (bool) $settings['debug_mode'] && current_user_can( 'manage_options' ),
			),
			'analytics'     => array(
				'mode'               => $settings['analytics_mode'],
				'requireConsent'     => (bool) $settings['analytics_require_consent'],
				'consentCookieName'  => $settings['analytics_consent_cookie_name'],
				'consentCookieValue' => $settings['analytics_consent_cookie_value'],
				'ga4Enabled'         => 'internal_external' === $settings['analytics_mode'] && ! empty( $settings['ga4_measurement_id'] ),
			),
			'i18n'          => array(
				'modalTitle'       => __( 'Select Your Location', 'restaurant-location-redirect' ),
				'modalSubtitle'    => __( 'Where are you ordering from?', 'restaurant-location-redirect' ),
				'changeLocation'   => __( 'Change Location', 'restaurant-location-redirect' ),
				'close'            => __( 'Close', 'restaurant-location-redirect' ),
				'noLocations'      => __( 'No locations are currently available.', 'restaurant-location-redirect' ),
			),
		);

		wp_localize_script( 'rlr-public', 'rlrConfig', $config );
	}

	/**
	 * Print the (visitor-agnostic) location selector modal markup.
	 */
	public function render_modal() {
		if ( ! $this->is_active() || ! RLR_Settings::get( 'enable_popup', true ) ) {
			return;
		}

		$locations = RLR_Location_Manager::get_active();
		include RLR_PLUGIN_DIR . 'templates/location-modal.php';
	}

	/**
	 * [rlr_change_location text="Change Location"]
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_change_location( $atts ) {
		$atts = shortcode_atts(
			array(
				'text' => __( 'Change Location', 'restaurant-location-redirect' ),
			),
			$atts,
			'rlr_change_location'
		);

		return sprintf(
			'<button type="button" class="rlr-change-location" data-rlr-change-location="1">%s</button>',
			esc_html( $atts['text'] )
		);
	}

	/**
	 * [rlr_location_switcher]
	 *
	 * @return string
	 */
	public function shortcode_location_switcher() {
		return '<button type="button" class="rlr-location-switcher" data-rlr-change-location="1">'
			. '<span class="rlr-location-switcher-label">' . esc_html__( 'Location', 'restaurant-location-redirect' ) . ':</span> '
			. '<span class="rlr-location-switcher-current" data-rlr-current-location>' . esc_html__( 'Select', 'restaurant-location-redirect' ) . '</span> '
			. '<span class="rlr-location-switcher-arrow" aria-hidden="true">&#9662;</span>'
			. '</button>';
	}

	/**
	 * [rlr_order_button text="Order Now"]
	 *
	 * A plugin-rendered, plugin-styled Order Now button, as an alternative
	 * to matching an existing theme/Elementor button via the configured CSS
	 * selector -- this way its layout is never at the mercy of an unknown
	 * theme's fixed-width/overflow CSS.
	 *
	 * Renders two separate, independently-clickable elements rather than one
	 * combined string: a small "state" pill showing the current location
	 * (click to change it, same as [rlr_change_location]) and the actual
	 * Order Now button. Both start inert. JS fills in the state pill's text
	 * and the button's href once a location is known (auto-detected or
	 * manually picked); until then, clicking the button opens the location
	 * popup instead of navigating.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode_order_button( $atts ) {
		$atts = shortcode_atts(
			array(
				'text'  => __( 'Order Now', 'restaurant-location-redirect' ),
				'class' => '',
			),
			$atts,
			'rlr_order_button'
		);

		$classes = trim( 'rlr-order-button ' . $atts['class'] );

		return '<span class="rlr-order-group">'
			. '<button type="button" class="rlr-order-state" data-rlr-change-location="1">'
			. '<span class="rlr-order-state-label" data-rlr-current-location-code>' . esc_html__( 'Select Location', 'restaurant-location-redirect' ) . '</span>'
			. '</button>'
			. sprintf(
				'<a href="#" class="%s" data-rlr-order-button="1"><span class="rlr-order-button-text">%s</span></a>',
				esc_attr( $classes ),
				esc_html( $atts['text'] )
			)
			. '</span>';
	}
}

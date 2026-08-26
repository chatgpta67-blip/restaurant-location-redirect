<?php
/**
 * WordPress admin UI: Settings → Location Order Redirect.
 *
 * A single top-level settings page with tabs (General, Locations,
 * Geolocation, Analytics, Debug & Help) rather than several separate menu
 * pages, to keep everything related to this plugin in one place.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Admin {

	const PAGE_SLUG = 'rlr-settings';

	/**
	 * Register hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_rlr_save_location', array( $this, 'handle_save_location' ) );
		add_action( 'admin_post_rlr_delete_location', array( $this, 'handle_delete_location' ) );
		add_action( 'admin_post_rlr_toggle_location', array( $this, 'handle_toggle_location' ) );
		add_action( 'admin_notices', array( $this, 'render_notices' ) );
	}

	/**
	 * Register the admin menu entry under Settings.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Location Order Redirect', 'restaurant-location-redirect' ),
			__( 'Location Order Redirect', 'restaurant-location-redirect' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the settings option with a sanitize callback.
	 */
	public function register_settings() {
		register_setting(
			'rlr_settings_group',
			RLR_Settings::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => RLR_Settings::defaults(),
			)
		);

		register_setting(
			'rlr_settings_group',
			'rlr_remove_data_on_uninstall',
			array(
				'type'              => 'boolean',
				'sanitize_callback' => 'rest_sanitize_boolean',
				'default'           => false,
			)
		);
	}

	/**
	 * Sanitize callback bridging the Settings API to RLR_Settings::sanitize().
	 *
	 * @param array $input Raw submitted settings.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return RLR_Settings::get_all();
		}
		return is_array( $input ) ? RLR_Settings::sanitize( $input ) : RLR_Settings::get_all();
	}

	/**
	 * Only load admin assets on our own settings screen.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'rlr-admin', RLR_PLUGIN_URL . 'admin/css/rlr-admin.css', array(), RLR_VERSION );
		wp_enqueue_script( 'rlr-admin', RLR_PLUGIN_URL . 'admin/js/rlr-admin.js', array(), RLR_VERSION, true );

		wp_localize_script(
			'rlr-admin',
			'rlrAdminConfig',
			array(
				'restUrl' => esc_url_raw( rest_url( RLR_REST::NAMESPACE_V1 ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
				'confirmDelete' => __( 'Delete this location? This cannot be undone.', 'restaurant-location-redirect' ),
			)
		);
	}

	/**
	 * Current tab, restricted to a known allow-list.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tabs = array( 'general', 'locations', 'geolocation', 'analytics', 'debug' );
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, $tabs, true ) ? $tab : 'general';
	}

	/**
	 * Render the settings page shell + the active tab's view.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'restaurant-location-redirect' ) );
		}

		$tab      = $this->current_tab();
		$settings = RLR_Settings::get_all();

		echo '<div class="wrap rlr-admin-wrap">';
		echo '<h1>' . esc_html__( 'Location Order Redirect', 'restaurant-location-redirect' ) . '</h1>';

		$this->render_tabs( $tab );

		$view_file = RLR_PLUGIN_DIR . 'admin/views/tab-' . $tab . '.php';
		if ( file_exists( $view_file ) ) {
			include $view_file;
		}

		echo '</div>';
	}

	/**
	 * Render the tab navigation.
	 *
	 * @param string $active_tab Currently active tab slug.
	 */
	private function render_tabs( $active_tab ) {
		$tabs = array(
			'general'     => __( 'General', 'restaurant-location-redirect' ),
			'locations'   => __( 'Locations', 'restaurant-location-redirect' ),
			'geolocation' => __( 'Geolocation', 'restaurant-location-redirect' ),
			'analytics'   => __( 'Analytics', 'restaurant-location-redirect' ),
			'debug'       => __( 'Debug & Help', 'restaurant-location-redirect' ),
		);

		echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => $slug,
				),
				admin_url( 'options-general.php' )
			);
			$class = 'nav-tab' . ( $active_tab === $slug ? ' nav-tab-active' : '' );
			printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
		}
		echo '</nav>';
	}

	/**
	 * Build the base "back to locations tab" redirect URL.
	 *
	 * @return string
	 */
	private function locations_url() {
		return add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => 'locations',
			),
			admin_url( 'options-general.php' )
		);
	}

	/**
	 * Redirect with a transient notice message.
	 *
	 * @param string $type    'success'|'error'.
	 * @param string $message Message text.
	 */
	private function redirect_with_notice( $type, $message ) {
		set_transient( 'rlr_admin_notice_' . get_current_user_id(), array( 'type' => $type, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( $this->locations_url() );
		exit;
	}

	/**
	 * Handle create/update location submissions.
	 */
	public function handle_save_location() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-location-redirect' ) );
		}
		check_admin_referer( 'rlr_save_location', 'rlr_location_nonce' );

		$id = isset( $_POST['location_id'] ) ? absint( $_POST['location_id'] ) : 0;

		if ( $id ) {
			$result = RLR_Location_Manager::update( $id, $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		} else {
			$result = RLR_Location_Manager::create( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( 'success', $id ? __( 'Location updated.', 'restaurant-location-redirect' ) : __( 'Location created.', 'restaurant-location-redirect' ) );
	}

	/**
	 * Handle location deletion.
	 */
	public function handle_delete_location() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-location-redirect' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'rlr_delete_location_' . $id );

		$deleted = RLR_Location_Manager::delete( $id );

		$this->redirect_with_notice(
			$deleted ? 'success' : 'error',
			$deleted ? __( 'Location deleted.', 'restaurant-location-redirect' ) : __( 'Could not delete location.', 'restaurant-location-redirect' )
		);
	}

	/**
	 * Handle activate/deactivate toggling.
	 */
	public function handle_toggle_location() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'restaurant-location-redirect' ) );
		}
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		check_admin_referer( 'rlr_toggle_location_' . $id );

		$toggled = RLR_Location_Manager::toggle_status( $id );

		$this->redirect_with_notice(
			$toggled ? 'success' : 'error',
			$toggled ? __( 'Location status updated.', 'restaurant-location-redirect' ) : __( 'Could not update status.', 'restaurant-location-redirect' )
		);
	}

	/**
	 * Print any pending transient admin notice.
	 */
	public function render_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . self::PAGE_SLUG !== $screen->id ) {
			return;
		}

		$key    = 'rlr_admin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );

		$class = 'success' === $notice['type'] ? 'notice-success' : 'notice-error';
		printf( '<div class="notice %s is-dismissible"><p>%s</p></div>', esc_attr( $class ), esc_html( $notice['message'] ) );
	}
}

<?php
/**
 * Contract that every IP geolocation provider must implement.
 *
 * To add a new provider: implement this interface in
 * includes/providers/class-rlr-provider-{slug}.php, then register it in
 * RLR_Geolocation::get_available_providers().
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface RLR_Geolocation_Provider {

	/**
	 * Machine-readable provider identifier (e.g. 'ip-api').
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Human-readable provider name for the admin UI.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Whether this provider requires an API key to function.
	 *
	 * @return bool
	 */
	public function requires_api_key();

	/**
	 * Look up geolocation data for an IP address.
	 *
	 * @param string $ip      IP address to look up.
	 * @param string $api_key API key, if required.
	 * @param int    $timeout Request timeout in seconds.
	 * @return array|WP_Error {
	 *     @type string $country      Country name.
	 *     @type string $country_code ISO 2-letter country code.
	 *     @type string $state        State/region name.
	 *     @type string $city         City name.
	 *     @type float  $latitude     Latitude.
	 *     @type float  $longitude    Longitude.
	 * } or WP_Error on failure.
	 */
	public function locate( $ip, $api_key, $timeout );
}

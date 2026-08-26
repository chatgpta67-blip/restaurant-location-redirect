<?php
/**
 * CRUD operations for restaurant locations (custom table wp_rlr_locations).
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RLR_Location_Manager {

	/**
	 * Get the fully-qualified locations table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'rlr_locations';
	}

	/**
	 * Fetch all locations, optionally filtered by status.
	 *
	 * @param array $args {
	 *     @type string $status  'active'|'inactive'|'' (all).
	 *     @type string $orderby Column to order by. Default 'sort_order'.
	 *     @type string $order   'ASC'|'DESC'.
	 * }
	 * @return array[] Array of associative arrays.
	 */
	public static function get_all( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'orderby' => 'sort_order',
			'order'   => 'ASC',
		);
		$args = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'sort_order', 'name', 'city', 'state', 'country', 'status', 'created_at', 'id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$table = self::table();
		$sql   = "SELECT * FROM {$table}";
		$where = array();
		$vals  = array();

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'active', 'inactive' ), true ) ) {
			$where[] = 'status = %s';
			$vals[]  = $args['status'];
		}

		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= " ORDER BY {$orderby} {$order}, id ASC";

		if ( $vals ) {
			$sql = $wpdb->prepare( $sql, $vals ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$results = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Get only active locations, safe to expose to the public frontend.
	 *
	 * @return array[]
	 */
	public static function get_active() {
		return self::get_all( array( 'status' => 'active' ) );
	}

	/**
	 * Get a single location by ID.
	 *
	 * @param int $id Location ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Get a single location by slug.
	 *
	 * @param string $slug Location slug.
	 * @return array|null
	 */
	public static function get_by_slug( $slug ) {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", sanitize_title( $slug ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $row ? $row : null;
	}

	/**
	 * Whether a slug is already used (optionally excluding one ID, for edits).
	 *
	 * @param string $slug       Slug to check.
	 * @param int    $exclude_id ID to exclude from the check.
	 * @return bool
	 */
	public static function slug_exists( $slug, $exclude_id = 0 ) {
		global $wpdb;
		$table = self::table();

		if ( $exclude_id ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id != %d", $slug, absint( $exclude_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} else {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE slug = %s", $slug ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return (int) $count > 0;
	}

	/**
	 * Validate and sanitize raw location input (from an admin form).
	 *
	 * @param array $input Raw input.
	 * @param int   $id    Existing location ID when editing, 0 when creating.
	 * @return array|WP_Error Sanitized fields, or WP_Error on validation failure.
	 */
	public static function sanitize_input( array $input, $id = 0 ) {
		$errors = new WP_Error();

		$name = isset( $input['name'] ) ? sanitize_text_field( wp_unslash( $input['name'] ) ) : '';
		if ( '' === trim( $name ) ) {
			$errors->add( 'rlr_missing_name', __( 'Location name is required.', 'restaurant-location-redirect' ) );
		}

		$city    = isset( $input['city'] ) ? sanitize_text_field( wp_unslash( $input['city'] ) ) : '';
		$state   = isset( $input['state'] ) ? sanitize_text_field( wp_unslash( $input['state'] ) ) : '';
		$country = isset( $input['country'] ) ? sanitize_text_field( wp_unslash( $input['country'] ) ) : '';

		if ( '' === trim( $city ) ) {
			$errors->add( 'rlr_missing_city', __( 'City is required for accurate location matching.', 'restaurant-location-redirect' ) );
		}
		if ( '' === trim( $country ) ) {
			$errors->add( 'rlr_missing_country', __( 'Country is required.', 'restaurant-location-redirect' ) );
		}

		$country_code = isset( $input['country_code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $input['country_code'] ) ) ) : '';
		$country_code = preg_replace( '/[^A-Z]/', '', $country_code );
		$country_code = substr( $country_code, 0, 2 );

		$order_url = isset( $input['order_url'] ) ? wp_unslash( $input['order_url'] ) : '';
		$validated_url = RLR_Helpers::validate_order_url( $order_url );
		if ( is_wp_error( $validated_url ) ) {
			$errors->add( 'rlr_invalid_url', $validated_url->get_error_message() );
		}

		$latitude  = null;
		$longitude = null;
		if ( isset( $input['latitude'] ) && '' !== trim( (string) $input['latitude'] ) ) {
			if ( ! is_numeric( $input['latitude'] ) || (float) $input['latitude'] < -90 || (float) $input['latitude'] > 90 ) {
				$errors->add( 'rlr_invalid_lat', __( 'Latitude must be a number between -90 and 90.', 'restaurant-location-redirect' ) );
			} else {
				$latitude = round( (float) $input['latitude'], 7 );
			}
		}
		if ( isset( $input['longitude'] ) && '' !== trim( (string) $input['longitude'] ) ) {
			if ( ! is_numeric( $input['longitude'] ) || (float) $input['longitude'] < -180 || (float) $input['longitude'] > 180 ) {
				$errors->add( 'rlr_invalid_lng', __( 'Longitude must be a number between -180 and 180.', 'restaurant-location-redirect' ) );
			} else {
				$longitude = round( (float) $input['longitude'], 7 );
			}
		}

		$status = isset( $input['status'] ) && 'inactive' === $input['status'] ? 'inactive' : 'active';

		$sort_order = isset( $input['sort_order'] ) ? intval( $input['sort_order'] ) : 0;

		if ( $errors->has_errors() ) {
			return $errors;
		}

		// On edit, keep the existing slug stable (visitors may have it stored
		// in a cookie/localStorage) unless the caller explicitly supplies a
		// new one. Only newly-created locations derive their slug from the name.
		$existing = $id ? self::get( $id ) : null;

		if ( isset( $input['slug'] ) && '' !== trim( wp_unslash( $input['slug'] ) ) ) {
			$slug_source = sanitize_title( wp_unslash( $input['slug'] ) );
		} elseif ( $existing ) {
			$slug_source = $existing['slug'];
		} else {
			$slug_source = sanitize_title( $name );
		}

		$slug = self::slug_exists( $slug_source, $id )
			? RLR_Helpers::unique_slug(
				$name,
				function ( $candidate ) use ( $id ) {
					return self::slug_exists( $candidate, $id );
				}
			)
			: $slug_source;

		return array(
			'name'         => $name,
			'slug'         => $slug,
			'city'         => $city,
			'state'        => $state,
			'country'      => $country,
			'country_code' => $country_code,
			'order_url'    => $validated_url,
			'latitude'     => $latitude,
			'longitude'    => $longitude,
			'status'       => $status,
			'sort_order'   => $sort_order,
		);
	}

	/**
	 * Create a new location.
	 *
	 * @param array $input Raw form input.
	 * @return int|WP_Error New location ID or error.
	 */
	public static function create( array $input ) {
		global $wpdb;

		$data = self::sanitize_input( $input, 0 );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$now = current_time( 'mysql', true );

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'name'         => $data['name'],
				'slug'         => $data['slug'],
				'city'         => $data['city'],
				'state'        => $data['state'],
				'country'      => $data['country'],
				'country_code' => $data['country_code'],
				'order_url'    => $data['order_url'],
				'latitude'     => $data['latitude'],
				'longitude'    => $data['longitude'],
				'status'       => $data['status'],
				'sort_order'   => $data['sort_order'],
				'created_at'   => $now,
				'updated_at'   => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'rlr_db_error', __( 'Could not save the location. Please try again.', 'restaurant-location-redirect' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update an existing location.
	 *
	 * @param int   $id    Location ID.
	 * @param array $input Raw form input.
	 * @return bool|WP_Error
	 */
	public static function update( $id, array $input ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id || ! self::get( $id ) ) {
			return new WP_Error( 'rlr_not_found', __( 'Location not found.', 'restaurant-location-redirect' ) );
		}

		$data = self::sanitize_input( $input, $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$updated = $wpdb->update(
			self::table(),
			array(
				'name'         => $data['name'],
				'slug'         => $data['slug'],
				'city'         => $data['city'],
				'state'        => $data['state'],
				'country'      => $data['country'],
				'country_code' => $data['country_code'],
				'order_url'    => $data['order_url'],
				'latitude'     => $data['latitude'],
				'longitude'    => $data['longitude'],
				'status'       => $data['status'],
				'sort_order'   => $data['sort_order'],
				'updated_at'   => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%d', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete a location.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Toggle a location's active/inactive status.
	 *
	 * @param int $id Location ID.
	 * @return bool
	 */
	public static function toggle_status( $id ) {
		$location = self::get( $id );
		if ( ! $location ) {
			return false;
		}

		global $wpdb;
		$new_status = 'active' === $location['status'] ? 'inactive' : 'active';

		return false !== $wpdb->update(
			self::table(),
			array(
				'status'     => $new_status,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Shape a DB row for safe exposure to the public frontend (no internal
	 * bookkeeping fields).
	 *
	 * @param array $row Raw DB row.
	 * @return array
	 */
	public static function to_public_array( array $row ) {
		return array(
			'id'      => (int) $row['id'],
			'slug'    => $row['slug'],
			'name'    => $row['name'],
			'city'    => $row['city'],
			'state'   => $row['state'],
			'country' => $row['country'],
			'orderUrl' => $row['order_url'],
		);
	}
}

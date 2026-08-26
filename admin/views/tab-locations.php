<?php
/**
 * Locations CRUD tab.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id       = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$editing       = $edit_id ? RLR_Location_Manager::get( $edit_id ) : null;
$form_defaults = array(
	'id'           => 0,
	'name'         => '',
	'city'         => '',
	'state'        => '',
	'country'      => '',
	'country_code' => '',
	'order_url'    => '',
	'latitude'     => '',
	'longitude'    => '',
	'status'       => 'active',
	'sort_order'   => 0,
);
$form = $editing ? wp_parse_args( $editing, $form_defaults ) : $form_defaults;
$all_locations = RLR_Location_Manager::get_all();
?>
<h2><?php echo $editing ? esc_html__( 'Edit Location', 'restaurant-location-redirect' ) : esc_html__( 'Add New Location', 'restaurant-location-redirect' ); ?></h2>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rlr-location-form">
	<input type="hidden" name="action" value="rlr_save_location" />
	<input type="hidden" name="location_id" value="<?php echo esc_attr( $form['id'] ); ?>" />
	<?php wp_nonce_field( 'rlr_save_location', 'rlr_location_nonce' ); ?>

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="rlr-loc-name"><?php esc_html_e( 'Location Name', 'restaurant-location-redirect' ); ?> <span class="description">*</span></label></th>
			<td><input type="text" id="rlr-loc-name" name="name" class="regular-text" required value="<?php echo esc_attr( $form['name'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Arizona', 'restaurant-location-redirect' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="rlr-loc-city"><?php esc_html_e( 'City', 'restaurant-location-redirect' ); ?> <span class="description">*</span></label></th>
			<td><input type="text" id="rlr-loc-city" name="city" class="regular-text" required value="<?php echo esc_attr( $form['city'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Phoenix', 'restaurant-location-redirect' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="rlr-loc-state"><?php esc_html_e( 'State / Region', 'restaurant-location-redirect' ); ?></label></th>
			<td><input type="text" id="rlr-loc-state" name="state" class="regular-text" value="<?php echo esc_attr( $form['state'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Arizona', 'restaurant-location-redirect' ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="rlr-loc-country"><?php esc_html_e( 'Country', 'restaurant-location-redirect' ); ?> <span class="description">*</span></label></th>
			<td>
				<input type="text" id="rlr-loc-country" name="country" class="regular-text" required value="<?php echo esc_attr( $form['country'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. United States', 'restaurant-location-redirect' ); ?>" />
				<input type="text" name="country_code" maxlength="2" class="small-text" style="text-transform:uppercase" value="<?php echo esc_attr( $form['country_code'] ); ?>" placeholder="<?php esc_attr_e( 'US', 'restaurant-location-redirect' ); ?>" />
				<p class="description"><?php esc_html_e( 'Country name plus its 2-letter ISO code (e.g. United States / US, United Kingdom / GB). The code improves match accuracy.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rlr-loc-url"><?php esc_html_e( 'Order URL', 'restaurant-location-redirect' ); ?> <span class="description">*</span></label></th>
			<td>
				<input type="url" id="rlr-loc-url" name="order_url" class="regular-text" required value="<?php echo esc_attr( $form['order_url'] ); ?>" placeholder="https://example.com/arizona-order" />
				<p class="description"><?php esc_html_e( 'The external ordering platform URL for this location. Any https:// or http:// URL, including third-party domains.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Latitude / Longitude', 'restaurant-location-redirect' ); ?></th>
			<td>
				<input type="text" name="latitude" class="small-text" value="<?php echo esc_attr( $form['latitude'] ); ?>" placeholder="33.4484" />
				<input type="text" name="longitude" class="small-text" value="<?php echo esc_attr( $form['longitude'] ); ?>" placeholder="-112.0740" />
				<p class="description"><?php esc_html_e( 'Optional. Enables proximity-based matching as a fallback when city/state text doesn\'t resolve to one location.', 'restaurant-location-redirect' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Status', 'restaurant-location-redirect' ); ?></th>
			<td>
				<select name="status">
					<option value="active" <?php selected( $form['status'], 'active' ); ?>><?php esc_html_e( 'Active', 'restaurant-location-redirect' ); ?></option>
					<option value="inactive" <?php selected( $form['status'], 'inactive' ); ?>><?php esc_html_e( 'Inactive', 'restaurant-location-redirect' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="rlr-loc-sort"><?php esc_html_e( 'Sort Order', 'restaurant-location-redirect' ); ?></label></th>
			<td><input type="number" id="rlr-loc-sort" name="sort_order" class="small-text" value="<?php echo esc_attr( $form['sort_order'] ); ?>" /></td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php echo $editing ? esc_html__( 'Update Location', 'restaurant-location-redirect' ) : esc_html__( 'Add Location', 'restaurant-location-redirect' ); ?></button>
		<?php if ( $editing ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'page' => 'rlr-settings', 'tab' => 'locations' ), admin_url( 'options-general.php' ) ) ); ?>"><?php esc_html_e( 'Cancel', 'restaurant-location-redirect' ); ?></a>
		<?php endif; ?>
	</p>
</form>

<hr />

<h2><?php esc_html_e( 'Locations', 'restaurant-location-redirect' ); ?></h2>

<table class="widefat striped rlr-locations-table">
	<thead>
		<tr>
			<th><?php esc_html_e( 'Location', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'City', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'State', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Country', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Order URL', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Status', 'restaurant-location-redirect' ); ?></th>
			<th><?php esc_html_e( 'Actions', 'restaurant-location-redirect' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $all_locations ) ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No locations yet. Add your first restaurant location above.', 'restaurant-location-redirect' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $all_locations as $loc ) : ?>
			<?php
			$edit_url   = add_query_arg( array( 'page' => 'rlr-settings', 'tab' => 'locations', 'edit' => $loc['id'] ), admin_url( 'options-general.php' ) );
			$delete_url = wp_nonce_url( admin_url( 'admin-post.php?action=rlr_delete_location&id=' . $loc['id'] ), 'rlr_delete_location_' . $loc['id'] );
			$toggle_url = wp_nonce_url( admin_url( 'admin-post.php?action=rlr_toggle_location&id=' . $loc['id'] ), 'rlr_toggle_location_' . $loc['id'] );
			?>
			<tr>
				<td><strong><?php echo esc_html( $loc['name'] ); ?></strong><br /><code><?php echo esc_html( $loc['slug'] ); ?></code></td>
				<td><?php echo esc_html( $loc['city'] ); ?></td>
				<td><?php echo esc_html( $loc['state'] ); ?></td>
				<td><?php echo esc_html( $loc['country'] ); ?> <?php echo $loc['country_code'] ? '(' . esc_html( $loc['country_code'] ) . ')' : ''; ?></td>
				<td><a href="<?php echo esc_url( $loc['order_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $loc['order_url'] ); ?></a></td>
				<td>
					<?php if ( 'active' === $loc['status'] ) : ?>
						<span class="rlr-status rlr-status-active"><?php esc_html_e( 'Active', 'restaurant-location-redirect' ); ?></span>
					<?php else : ?>
						<span class="rlr-status rlr-status-inactive"><?php esc_html_e( 'Inactive', 'restaurant-location-redirect' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'restaurant-location-redirect' ); ?></a> |
					<a href="<?php echo esc_url( $toggle_url ); ?>"><?php echo 'active' === $loc['status'] ? esc_html__( 'Deactivate', 'restaurant-location-redirect' ) : esc_html__( 'Activate', 'restaurant-location-redirect' ); ?></a> |
					<a href="<?php echo esc_url( $delete_url ); ?>" class="rlr-delete-location" style="color:#b32d2e;"><?php esc_html_e( 'Delete', 'restaurant-location-redirect' ); ?></a>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<?php
/**
 * Location selection modal markup.
 *
 * This markup is identical for every visitor (only active locations are
 * listed) so it is safe to render server-side even behind a page cache.
 * The decision of WHICH location to pre-select, and whether to show this
 * modal at all, happens entirely client-side in public/js/rlr-public.js.
 *
 * @var array[] $locations Active location rows.
 *
 * @package Restaurant_Location_Redirect
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="rlr-modal-overlay" class="rlr-modal-overlay" hidden aria-hidden="true">
	<div id="rlr-modal" class="rlr-modal" role="dialog" aria-modal="true" aria-labelledby="rlr-modal-title">
		<button type="button" class="rlr-modal-close" data-rlr-close-modal aria-label="<?php esc_attr_e( 'Close', 'restaurant-location-redirect' ); ?>">&times;</button>

		<h2 id="rlr-modal-title" class="rlr-modal-title"><?php esc_html_e( 'Select Your Location', 'restaurant-location-redirect' ); ?></h2>
		<p class="rlr-modal-subtitle"><?php esc_html_e( 'Where are you ordering from?', 'restaurant-location-redirect' ); ?></p>

		<div class="rlr-modal-notice" data-rlr-modal-notice hidden></div>

		<ul class="rlr-location-list">
			<?php if ( empty( $locations ) ) : ?>
				<li class="rlr-location-empty"><?php esc_html_e( 'No locations are currently available.', 'restaurant-location-redirect' ); ?></li>
			<?php endif; ?>
			<?php foreach ( $locations as $location ) : ?>
				<li class="rlr-location-item">
					<button
						type="button"
						class="rlr-location-option"
						data-rlr-location-id="<?php echo esc_attr( $location['id'] ); ?>"
						data-rlr-location-slug="<?php echo esc_attr( $location['slug'] ); ?>"
						data-rlr-location-url="<?php echo esc_url( $location['order_url'] ); ?>"
						data-rlr-location-name="<?php echo esc_attr( $location['name'] ); ?>"
					>
						<span class="rlr-location-name"><?php echo esc_html( $location['name'] ); ?></span>
						<?php if ( ! empty( $location['city'] ) ) : ?>
							<span class="rlr-location-meta">
								<?php echo esc_html( trim( $location['city'] . ( ! empty( $location['state'] ) ? ', ' . $location['state'] : '' ) ) ); ?>
							</span>
						<?php endif; ?>
					</button>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</div>

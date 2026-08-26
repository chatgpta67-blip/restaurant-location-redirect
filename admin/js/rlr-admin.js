/**
 * Admin UI behavior: delete confirmation, provider API key field toggling.
 *
 * @package Restaurant_Location_Redirect
 */

( function () {
	'use strict';

	document.addEventListener( 'click', function ( evt ) {
		var deleteLink = evt.target.closest( '.rlr-delete-location' );
		if ( deleteLink ) {
			var message = ( typeof rlrAdminConfig !== 'undefined' && rlrAdminConfig.confirmDelete )
				? rlrAdminConfig.confirmDelete
				: 'Delete this location?';
			if ( ! window.confirm( message ) ) {
				evt.preventDefault();
			}
		}
	} );

	function toggleProviderKeyRows() {
		var select = document.getElementById( 'rlr-geo-provider' );
		if ( ! select ) {
			return;
		}
		var rows = document.querySelectorAll( '.rlr-provider-key-row' );
		rows.forEach( function ( row ) {
			row.style.display = row.getAttribute( 'data-provider' ) === select.value ? '' : 'none';
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var select = document.getElementById( 'rlr-geo-provider' );
		if ( select ) {
			toggleProviderKeyRows();
			select.addEventListener( 'change', toggleProviderKeyRows );
		}
	} );
} )();

( function( $ ) {
	'use strict';

	$( function() {
		$( '.maruri-color-picker' ).wpColorPicker();

		$( document ).on( 'click', '.maruri-color-reset', function( event ) {
			var $button = $( event.currentTarget );
			var fieldName = $button.data( 'target-name' );
			var defaultColor = $button.data( 'default-color' );
			var $input = $( '[name="' + fieldName + '"]' );

			if ( ! $input.length ) {
				return;
			}

			$input.val( defaultColor );

			if ( $input.hasClass( 'wp-color-picker' ) ) {
				$input.wpColorPicker( 'color', defaultColor );
			}
		} );
	} );
}( jQuery ) );

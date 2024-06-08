jQuery(function($){

	$(document).ready(function(){

		$( 'select#bonips-email-instance' ).change(function(e){

			var selectedevent = $(this).find( ':selected' );
			console.log( selectedevent.val() );
			if ( selectedevent.val() == 'custom' ) {

				$( '#reference-selection' ).show();

			}
			else {

				$( '#reference-selection' ).hide();

			}

		});

		$( 'select#bonips-email-reference' ).change(function(e){

			var selectedevent = $(this).find( ':selected' );
			if ( selectedevent.val() == 'bonips_custom' ) {

				$( '#custom-reference-selection' ).show();
				$( '#bonips-email-custom-ref' ).focus();

			}
			else {

				$( '#custom-reference-selection' ).hide();
				$( '#bonips-email-custom-ref' ).blur();

			}

		});

	});

});
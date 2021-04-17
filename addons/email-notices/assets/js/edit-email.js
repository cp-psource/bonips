jQuery(function($){

	$(document).ready(function(){

		$( 'select#bonipress-email-instance' ).change(function(e){

			var selectedevent = $(this).find( ':selected' );
			console.log( selectedevent.val() );
			if ( selectedevent.val() == 'custom' ) {

				$( '#reference-selection' ).show();

			}
			else {

				$( '#reference-selection' ).hide();

			}

		});

		$( 'select#bonipress-email-reference' ).change(function(e){

			var selectedevent = $(this).find( ':selected' );
			if ( selectedevent.val() == 'bonipress_custom' ) {

				$( '#custom-reference-selection' ).show();
				$( '#bonipress-email-custom-ref' ).focus();

			}
			else {

				$( '#custom-reference-selection' ).hide();
				$( '#bonipress-email-custom-ref' ).blur();

			}

		});

	});

});
/**
 * boniPS Transfer jQuery
 * Handles transfer requests and autocomplete of recipient search.
 *
 * @requires jQuery
 * @requires jQuery UI
 * @requires jQuery Autocomplete
 * @since 0.1
 * @version 1.4
 */
jQuery(function($){
	// Transfer function
	var transfer_creds = function( submitted_form, label ) {
		$.ajax({
			type : "POST",
			data : {
				action    : 'bonips-transfer-creds',
				form      : submitted_form,
				token     : boniPS.token
			},
			dataType : "JSON",
			url : boniPS.ajaxurl,
			// Before we start
			beforeSend : function() {
				// Prevent users from clicking multiple times
				$( '.bonips-click' ).val( boniPS.working );
				$( '.bonips-click' ).attr( 'disabled', 'disabled' );
			},
			// On Successful Communication
			success    : function( data ) {
				$( '.bonips-click' ).val( label );
				$( '.bonips-click' ).removeAttr( 'disabled' );

				// Error
				if ( boniPS[ data ] !== undefined )
					alert( boniPS[ data ] );

				// Completed
				else if ( data == 'ok' ) {
					alert( boniPS.completed );

					// If reload is set
					if ( boniPS.reload == '1' )
						location.reload();
				}

				// WP Nonce no longer valid / we have been logged out
				else if ( data == '-1' || data == 0 )
					location.reload();

				// All else
				else {
					$('.bonips-click').attr( 'value', data );
					if ( boniPS.reload == '1' )
						location.reload();
				}
			}
		});
	};
	
	// Autocomplete
	// @api http://api.jqueryui.com/autocomplete/
	var cache = {};
	$( 'input.bonips-autofill' ).autocomplete({
		minLength: 2,
		source: function( request, response ) {
			var term = request.term;
			if ( term in cache ) {
				response( cache[ term ] );
				return;
			}
			
			var send = {
				action : "bonips-autocomplete",
				token  : boniPS.atoken,
				string : request
			};
			$.getJSON( boniPS.ajaxurl, send, function( data, status, xhr ) {
				cache[ term ] = data;
				// Debug - uncomment to use
				//console.log( data );
				//console.log( status );
				response( data );
			});
		},
		messages: {
			noResults: '',
			results: function() {}
		},
		appendTo : 'div.transfer-to'
	});
	
	// Attempt Transfer
	$( '.bonips-click' ).on('click', function(){

		// The form
		var the_form = $(this).parent().parent().parent();

		// To:
		var receipient = $(this).parent().prev().children( 'div' ).children( 'input' ).val();

		// Amount:
		var creds = $(this).prev().children( 'input[name=bonips-transfer-amount]' ).val();

		// If elements are not emepty attempt transfer
		if ( receipient != '' && creds != '' ) {
			transfer_creds( the_form.serialize(), $(this).val() );
		}

	});
});
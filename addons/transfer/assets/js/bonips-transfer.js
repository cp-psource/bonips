/**
 * boniPS Transfer jQuery
 * Handles transfer requests and autocomplete of recipient search.
 *
 * @requires jQuery
 * @requires jQuery UI
 * @requires jQuery Autocomplete
 * @since 0.1
 * @version 1.5.2
 */
(function($) {

	var bonips_transfer_cache  = {};

	// Autocomplete
	// @api http://api.jqueryui.com/autocomplete/
	var bonips_transfer_autofill = $( 'input.bonips-autofill' ).autocomplete({

		minLength : 2,
		source    : function( request, response ) {

			var term = request.term;
			if ( term in bonips_transfer_cache ) {
				response( bonips_transfer_cache[ term ] );
				return;
			}
			
			var send = {
				action : "bonips-autocomplete",
				token  : boniPSTransfer.token,
				string : request
			};

			$.getJSON( boniPSTransfer.ajaxurl, send, function( data, status, xhr ) {
				bonips_transfer_cache[ term ] = data;
				response( data );
			});

		},
		messages: {
			noResults : '',
			results   : function() {}
		},
		position: { my : "right top", at: "right bottom" }

	});

	$( 'input.bonips-autofill' ).click(function(){

		if ( boniPSTransfer.autofill == 'none' ) return false;

		var formfieldid = $(this).data( 'form' );
		bonips_transfer_autofill.autocomplete( "option", "appendTo", '#bonips-transfer-form-' + formfieldid + ' .select-recipient-wrapper' );
		console.log( formfieldid );

	});

	// Transfer form submissions
	// @since 1.6.3
	$( 'html body' ).on( 'submit', 'form.bonips-transfer-form', function(e){

		console.log( 'new transfer' );

		var transferform = $(this);
		var formrefid    = transferform.data( 'ref' );
		var formid       = '#bonips-transfer-form-' + formrefid;
		var submitbutton = $( formid + ' input.bonips-submit-transfer' );
		var buttonlabel  = submitbutton.val();

		e.preventDefault();

		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-new-transfer',
				form      : transferform.serialize(),
			},
			dataType   : "JSON",
			url        : boniPSTransfer.ajaxurl,
			beforeSend : function() {

				$( formid + 'input.form-control' ).each(function(index){
					$(this).attr( 'disabled', 'disabled' );
				});

				submitbutton.attr( 'disabled', 'disabled' );
				submitbutton.val( boniPSTransfer.working );

			},
			success    : function( response ) {

				console.log( response );

				$( formid + ' input.form-control' ).each(function(index){
					$(this).removeAttr( 'disabled' );
				});

				submitbutton.removeAttr( 'disabled', 'disabled' );
				submitbutton.val( buttonlabel );

				if ( response.success !== undefined ) {

					if ( response.success ) {

						// Allow customizations to present custom success messages
						if ( response.data.message !== undefined && response.data.message != '' )
							alert( response.data.message );
						else
							alert( boniPSTransfer.completed );

						if ( $( response.data.css ) !== undefined )
							$( response.data.css ).empty().html( response.data.balance );

						// Reset form
						$( formid + ' input.form-control' ).each(function(index){
							$(this).val( '' );
						});

						$( formid + ' select' ).each(function(index){
							var selecteditem = $(this).find( ':selected' );
							if ( selecteditem !== undefined )
								selecteditem.removeAttr( 'selected' );
						});

						// If we require reload after submission, do so now
						if ( boniPSTransfer.reload == '1' ) location.reload();

					}

					else if ( boniPSTransfer[ response.data ] !== undefined )
						alert( boniPSTransfer[ response.data ] );

				}

			}

		});

		return false;

	});

})( jQuery );
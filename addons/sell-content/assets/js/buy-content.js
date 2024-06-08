/**
 * boniPS Sell Content
 * @since 1.1
 * @version 1.2
 */
(function($) {

	var buying = false;

	$( '.bonips-sell-this-wrapper' ).on( 'click', '.bonips-buy-this-content-button', function(){

		if ( buying === true ) return false;

		buying = true;

		var button      = $(this);
		var post_id     = button.data( 'pid' );
		var point_type  = button.data( 'type' );
		var buttonlabel = button.html();
		var content_for_sale = $( '#bonips-buy-content' + post_id );

		$.ajax({
			type : "POST",
			data : {
				action    : 'bonips-buy-content',
				token     : boniPSBuyContent.token,
				postid    : post_id,
				ctype     : point_type
			},
			dataType   : "JSON",
			url        : boniPSBuyContent.ajaxurl,
			beforeSend : function() {

				button.attr( 'disabled', 'disabled' ).html( boniPSBuyContent.working );

			},
			success    : function( response ) {

				if ( response.success === undefined || ( response.success === true && boniPSBuyContent.reload === '1' ) )
					location.reload();

				else {

					if ( response.success ) {
						content_for_sale.fadeOut(function(){
							content_for_sale.removeClass( 'bonips-sell-this-wrapper bonips-sell-entire-content bonips-sell-partial-content' ).empty().append( response.data ).fadeIn();
						});
					}

					else {

						button.removeAttr( 'disabled' ).html( buttonlabel );

						if ( response.data != '' )
							alert( response.data );

					}

				}

				console.log( response );

			},
			complete : function(){

				buying = false;

			}
		});

	});

})( jQuery );
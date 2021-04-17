/**
 * boniPRESS Sell Content
 * @since 1.1
 * @version 1.2
 */
(function($) {

	var buying = false;

	$( '.bonipress-sell-this-wrapper' ).on( 'click', '.bonipress-buy-this-content-button', function(){

		if ( buying === true ) return false;

		buying = true;

		var button      = $(this);
		var post_id     = button.data( 'pid' );
		var point_type  = button.data( 'type' );
		var buttonlabel = button.html();
		var content_for_sale = $( '#bonipress-buy-content' + post_id );

		$.ajax({
			type : "POST",
			data : {
				action    : 'bonipress-buy-content',
				token     : boniPRESSBuyContent.token,
				postid    : post_id,
				ctype     : point_type
			},
			dataType   : "JSON",
			url        : boniPRESSBuyContent.ajaxurl,
			beforeSend : function() {

				button.attr( 'disabled', 'disabled' ).html( boniPRESSBuyContent.working );

			},
			success    : function( response ) {

				if ( response.success === undefined || ( response.success === true && boniPRESSBuyContent.reload === '1' ) )
					location.reload();

				else {

					if ( response.success ) {
						content_for_sale.fadeOut(function(){
							content_for_sale.removeClass( 'bonipress-sell-this-wrapper bonipress-sell-entire-content bonipress-sell-partial-content' ).empty().append( response.data ).fadeIn();
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
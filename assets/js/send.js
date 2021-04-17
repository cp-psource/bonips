/**
 * boniPRESS
 * Handle bonipress_send shortcode buttons.
 * @since 0.1
 * @version 1.3
 */
jQuery(function($) {

	$( 'button.bonipress-send-points-button' ).click(function(){

		var button        = $(this);
		var originallabel = button.text();

		$.ajax({
			type : "POST",
			data : {
				action    : 'bonipress-send-points',
				amount    : button.data( 'amount' ),
				recipient : button.data( 'to' ),
				log       : button.data( 'log' ),
				reference : button.data( 'ref' ),
				type      : button.data( 'type' ),
				token     : boniPRESSsend.token
			},
			dataType   : "JSON",
			url        : boniPRESSsend.ajaxurl,
			beforeSend : function() {

				button.attr( 'disabled', 'disabled' ).text( boniPRESSsend.working );

			},
			success    : function( data ) {

				if ( data == 'done' ) {

					button.text( boniPRESSsend.done );
					setTimeout( function(){
						button.removeAttr( 'disabled' ).text( originallabel );
					}, 2000 );

				}

				else if ( data == 'zero' ) {

					button.text( boniPRESSsend.done );
					setTimeout( function(){

						$( 'button.bonipress-send-points-button' ).each(function(){
							$(this).attr( 'disabled', 'disabled' ).hide();
						});

					}, 2000 );

				}
				else {

					button.text( boniPRESSsend.error );
					setTimeout( function(){
						button.removeAttr( 'disabled' ).text( originallabel );
					}, 2000 );

				}

			}
		});

	});

});
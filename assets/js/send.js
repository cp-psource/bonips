/**
 * boniPS
 * Handle bonips_send shortcode buttons.
 * @since 0.1
 * @version 1.3
 */
jQuery(function($) {

	$( 'button.bonips-send-points-button' ).on('click', function(){

		var button        = $(this);
		var originallabel = button.text();

		$.ajax({
			type : "POST",
			data : {
				action    : 'bonips-send-points',
				amount    : button.data( 'amount' ),
				recipient : button.data( 'to' ),
				log       : button.data( 'log' ),
				reference : button.data( 'ref' ),
				type      : button.data( 'type' ),
				token     : boniPSsend.token
			},
			dataType   : "JSON",
			url        : boniPSsend.ajaxurl,
			beforeSend : function() {

				button.attr( 'disabled', 'disabled' ).text( boniPSsend.working );

			},
			success    : function( data ) {

				if ( data == 'done' ) {

					button.text( boniPSsend.done );
					setTimeout( function(){
						button.removeAttr( 'disabled' ).text( originallabel );
					}, 2000 );

				}

				else if ( data == 'zero' ) {

					button.text( boniPSsend.done );
					setTimeout( function(){

						$( 'button.bonips-send-points-button' ).each(function(){
							$(this).attr( 'disabled', 'disabled' ).hide();
						});

					}, 2000 );

				}
				else {

					button.text( boniPSsend.error );
					setTimeout( function(){
						button.removeAttr( 'disabled' ).text( originallabel );
					}, 2000 );

				}

			}
		});

	});

});
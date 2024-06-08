/**
 * boniPS Points for Link Clicks jQuery Scripts
 * @contributors Kevin Reeves
 * @since 0.1
 * @version 1.7.1
 */
jQuery(function($) {

	$( '.bonips-points-link' ).on('click', function(){

		var bonipslink      = $(this);
		var linkdestination = bonipslink.attr( 'href' );
		var target          = bonipslink.attr( 'target' );
		if ( typeof target === 'undefined' ) {
			target = 'self';
		}

		$.ajax({
			type     : "POST",
			data     : {
				action : 'bonips-click-points',
				url    : linkdestination,
				token  : boniPSlink.token,
				etitle : bonipslink.text(),
				ctype  : bonipslink.attr( 'data-type' ),
				key    : bonipslink.attr( 'data-token' )
			},
			dataType : "JSON",
			url      : boniPSlink.ajaxurl,
			success  : function( response ) {
				console.log( response );
				if ( target == 'self' || target == '_self' )
					window.location.href = linkdestination;
			}
		});

		if ( target == 'self' || target == '_self' ) return false;

	});

});
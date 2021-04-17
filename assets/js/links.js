/**
 * boniPRESS Points for Link Clicks jQuery Scripts
 * @contributors Kevin Reeves
 * @since 0.1
 * @version 1.7.1
 */
jQuery(function($) {

	$( '.bonipress-points-link' ).click(function(){

		var bonipresslink      = $(this);
		var linkdestination = bonipresslink.attr( 'href' );
		var target          = bonipresslink.attr( 'target' );
		if ( typeof target === 'undefined' ) {
			target = 'self';
		}

		$.ajax({
			type     : "POST",
			data     : {
				action : 'bonipress-click-points',
				url    : linkdestination,
				token  : boniPRESSlink.token,
				etitle : bonipresslink.text(),
				ctype  : bonipresslink.attr( 'data-type' ),
				key    : bonipresslink.attr( 'data-token' )
			},
			dataType : "JSON",
			url      : boniPRESSlink.ajaxurl,
			success  : function( response ) {
				console.log( response );
				if ( target == 'self' || target == '_self' )
					window.location.href = linkdestination;
			}
		});

		if ( target == 'self' || target == '_self' ) return false;

	});

});
/**
 * boniPS Sell Content
 * @since 1.1
 * @version 1.0
 */
jQuery(function($) {
	var bonips_buy_content = function( button, label ) {
		wrapper = button.parents( 'div.bonips-content-forsale' );
		$.ajax({
			type : "POST",
			data : {
				action    : 'bonips-buy-content',
				postid    : button.attr( 'data-id' ),
				token     : boniPSsell.token
			},
			dataType : "HTML",
			url : boniPSsell.ajaxurl,
			// Before we start
			beforeSend : function() {
				button.attr( 'value', boniPSsell.working );
				button.attr( 'disabled', 'disabled' );
				wrapper.slideUp();
			},
			// On Successful Communication
			success    : function( data ) {
				wrapper.empty();
				wrapper.append( data );
				wrapper.slideDown();
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				button.attr( 'value', 'Upps!' );
				button.removeAttr( 'disabled' );
				wrapper.slideDown();
				// Debug - uncomment to use
				console.log( jqXHR );
				console.log( textStatus );
				console.log( errorThrown );
			}
		});
	};
	
	$('.bonips-sell-this-button').on('click', function(){
		bonips_buy_content( $(this), $(this).attr( 'value' ) );
	});
});
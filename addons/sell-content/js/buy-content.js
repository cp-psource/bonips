/**
 * boniPRESS Sell Content
 * @since 1.1
 * @version 1.0
 */
jQuery(function($) {
	var bonipress_buy_content = function( button, label ) {
		wrapper = button.parents( 'div.bonipress-content-forsale' );
		$.ajax({
			type : "POST",
			data : {
				action    : 'bonipress-buy-content',
				postid    : button.attr( 'data-id' ),
				token     : boniPRESSsell.token
			},
			dataType : "HTML",
			url : boniPRESSsell.ajaxurl,
			// Before we start
			beforeSend : function() {
				button.attr( 'value', boniPRESSsell.working );
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
	
	$('.bonipress-sell-this-button').click(function(){
		bonipress_buy_content( $(this), $(this).attr( 'value' ) );
	});
});
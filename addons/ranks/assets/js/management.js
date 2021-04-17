/**
 * boniPRESS Management Scripts
 * @since 1.3
 * @version 1.1.1
 */
jQuery(function($){
	
	var bonipress_action_delete_ranks = function( button, pointtype ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'bonipress-action-delete-ranks',
				token  : boniPRESS_Ranks.token,
				ctype  : pointtype
			},
			dataType : "JSON",
			url : boniPRESS_Ranks.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESS_Ranks.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					$( 'input#bonipress-ranks-no-of-ranks' ).val( data.rows );
					button.val( boniPRESSmanage.done );
					button.removeClass( 'button-primary' );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
				}
			},
			error   : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( textStatus + ':' + errorThrown );
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	};
	
	$( 'input#bonipress-manage-action-reset-ranks' ).click(function(){
		// Confirm action
		if ( confirm( boniPRESS_Ranks.confirm_del ) ) {
			bonipress_action_delete_ranks( $(this), $(this).data( 'type' ) );
		}
	});
	
	var bonipress_action_assign_ranks = function( button, pointtype ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'bonipress-action-assign-ranks',
				token  : boniPRESS_Ranks.token,
				ctype  : pointtype
			},
			dataType : "JSON",
			url : boniPRESS_Ranks.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESS_Ranks.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					button.val( boniPRESSmanage.done );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
				}
			},
			error   : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				console.log( textStatus + ':' + errorThrown );
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	};
	
	$( 'input#bonipress-manage-action-assign-ranks' ).click(function(){
		// Confirm action
		if ( confirm( boniPRESS_Ranks.confirm_assign ) ) {
			bonipress_action_assign_ranks( $(this), $(this).data( 'type' ) );
		}
	});

});
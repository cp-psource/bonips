/**
 * boniPS Management Scripts
 * @since 1.3
 * @version 1.1.1
 */
jQuery(function($){
	
	var bonips_action_delete_ranks = function( button, pointtype ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'bonips-action-delete-ranks',
				token  : boniPS_Ranks.token,
				ctype  : pointtype
			},
			dataType : "JSON",
			url : boniPS_Ranks.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPS_Ranks.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					$( 'input#bonips-ranks-no-of-ranks' ).val( data.rows );
					button.val( boniPSmanage.done );
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
	
	$( 'input#bonips-manage-action-reset-ranks' ).on('click', function(){
		// Confirm action
		if ( confirm( boniPS_Ranks.confirm_del ) ) {
			bonips_action_delete_ranks( $(this), $(this).data( 'type' ) );
		}
	});
	
	var bonips_action_assign_ranks = function( button, pointtype ) {
		var label = button.val();
		$.ajax({
			type : "POST",
			data : {
				action : 'bonips-action-assign-ranks',
				token  : boniPS_Ranks.token,
				ctype  : pointtype
			},
			dataType : "JSON",
			url : boniPS_Ranks.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPS_Ranks.working );
				button.attr( 'disabled', 'disabled' );
			},
			success : function( data ) {
				console.log( data );
				
				if ( data.status == 'OK' ) {
					button.val( boniPSmanage.done );
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
	
	$( 'input#bonips-manage-action-assign-ranks' ).on('click', function(){
		// Confirm action
		if ( confirm( boniPS_Ranks.confirm_assign ) ) {
			bonips_action_assign_ranks( $(this), $(this).data( 'type' ) );
		}
	});

});
/**
 * boniPS Inline Edit
 * @since 1.2
 * @version 1.2
 */
jQuery(function($) {

	var username = '';
	var user_id = '';
	var current = '';
	var current_el = '';

	/**
	 * Setup Points Editor Modal
	 */
	$(document).ready( function() {
		$('#edit-bonips-balance').dialog({
			dialogClass : 'bonips-update-balance',
			draggable   : true,
			autoOpen    : false,
			title       : boniPSedit.title,
			closeText   : boniPSedit.close,
			modal       : true,
			width       : 500,
			height      : 'auto',
			resizable   : false,
			show        : {
				effect     : 'slide',
				direction  : 'up',
				duration   : 250
			},
			hide        : {
				effect     : 'slide',
				direction  : 'up',
				duration   : 250
			}
		});
	});

	/**
	 * Edit Points Trigger
	 */
	$( '.bonips-open-points-editor' ).click( function() {
		
		$( '#edit-bonips-balance' ).dialog( 'open' );
		
		$( '#edit-bonips-balance #bonips-username' ).empty().text( $(this).attr( 'data-username' ) );
		
		$( '#edit-bonips-balance #bonips-userid' ).empty().text( $(this).attr( 'data-userid' ) );
		
		$( '#edit-bonips-balance #bonips-current' ).empty().text( $(this).attr( 'data-current' ) );
		
		$( '#bonips-update-users-balance-type' ).val( $(this).attr( 'data-type' ) );
	});

	/**
	 * Update Balance AJAX Caller
	 */
	$( '#bonips-update-users-balance-submit' ).click( function() {
		var button = $(this);
		var label = button.val();
		var current_el = $( '#edit-bonips-balance #bonips-current' );
		var user_id = $( '#edit-bonips-balance #bonips-userid' ).text();
		var amount_el = $( 'input#bonips-update-users-balance-amount' );
		var entry_el = $( 'input#bonips-update-users-balance-entry' );
		var type_el = $( '#bonips-update-users-balance-type' );
		
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-inline-edit-users-balance',
				token     : $( 'input#bonips-update-users-balance-token' ).val(),
				user      : user_id,
				amount    : amount_el.val(),
				entry     : entry_el.val(),
				type      : type_el.val()
			},
			dataType   : "JSON",
			url        : boniPSedit.ajaxurl,
			// Before we start
			beforeSend : function() {
				current_el.removeClass( 'done' );
				entry_el.removeClass( 'error' );
				amount_el.removeClass( 'error' );
				
				button.attr( 'value', boniPSedit.working );
				button.attr( 'disabled', 'disabled' );
			},
			// On Successful Communication
			success    : function( response ) {
				// Debug
				console.log( response );
				
				if ( response.success ) {
					current_el.addClass( 'done' );
					current_el.text( response.data );
					amount_el.val( '' );
					entry_el.val( '' );
					$( 'div#bonips-user-' + user_id + '-balance-' + type_el.val() + ' span' ).empty().html( response.data );
				}
				else {
					if ( response.data.error == 'ERROR_1' ) {
						$( '#edit-bonips-balance' ).dialog( 'destroy' );
					}
					else if ( response.data.error == 'ERROR_2' ) {
						alert( response.data.message );
						amount_el.val( '' );
						entry_el.val( '' );
					}
					else  {
						entry_el.addClass( 'error' );
						entry_el.attr( 'title', response.data.message );
					}
				}
				
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			},
			// Error (sent to console)
			error      : function( jqXHR, textStatus, errorThrown ) {
				// Debug
				//console.log( jqXHR + ':' + textStatus + ':' + errorThrown );
				
				button.attr( 'value', label );
				button.removeAttr( 'disabled' );
			}
		});
	});
});
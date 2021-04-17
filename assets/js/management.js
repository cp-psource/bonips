/**
 * boniPRESS Management Scripts
 * @since 1.3
 * @version 1.2
 */
jQuery(function($) {

	/**
	 * Empty Log AJAX Caller
	 */
	var bonipress_action_empty_log = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-action-empty-log',
				token     : boniPRESSmanage.token,
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPRESSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success    : function( response ) {
				if ( response.success ) {
					$( 'input#bonipress-manage-table-rows' ).val( response.data );
					button.val( boniPRESSmanage.done );
					button.removeClass( 'button-primary' );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
					alert( response.data );
				}
			}
		});
	};

	/**
	 * Empty Log Trigger
	 */
	$( 'input#bonipress-manage-action-empty-log' ).click(function(){
		// Confirm action
		if ( confirm( boniPRESSmanage.confirm_log ) )
			bonipress_action_empty_log( $(this) );
	});

	/**
	 * Reset Balance AJAX Caller
	 */
	var bonipress_action_reset_balance = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-action-reset-accounts',
				token     : boniPRESSmanage.token,
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPRESSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success    : function( response ) {
				if ( response.success ) {
					button.val( response.data );
					button.removeClass( 'button-primary' );
				}
				else {
					button.val( label );
					button.removeAttr( 'disabled' );
					alert( response.data );
				}
			}
		});
	};

	/**
	 * Reset Balance Trigger
	 */
	$( 'input#bonipress-manage-action-reset-accounts' ).click(function(){
		// Confirm action
		if ( confirm( boniPRESSmanage.confirm_reset ) )
			bonipress_action_reset_balance( $(this) );
	});

	/**
	 * Export Balances Modal
	 */
	$('#export-points').dialog({
		dialogClass : 'bonipress-export-points',
		draggable   : false,
		autoOpen    : false,
		closeText   : boniPRESSmanage.export_close,
		title       : boniPRESSmanage.export_title,
		modal       : true,
		width       : 500,
		resizable   : false,
		show        : { effect: 'slide', direction: 'up', duration: 250 },
		hide        : { effect: 'slide', direction: 'up', duration: 250 }
	});

	/**
	 * Export balances Modal Trigger
	 */
	$( '#bonipress-export-users-points' ).click( function() {
		$( '#export-points' ).dialog( 'open' );
	});

	/**
	 * Export Balances AJAX Caller
	 */
	var bonipress_action_export_balances = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonipress-action-export-balances',
				token     : boniPRESSmanage.token,
				identify  : $( '#bonipress-export-identify-by' ).val(),
				log_temp  : $( '#bonipress-export-log-template' ).val(),
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPRESSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success    : function( response ) {
				// Debug
				//console.log( response );

				if ( response.success ) {
					setTimeout(function(){
						window.location.href = response.data;
						button.val( boniPRESSmanage.done );
					}, 2000 );
					setTimeout(function(){
						button.removeAttr( 'disabled' );
						button.val( label );
					}, 4000 );
				}
				else {
					button.val( label );
					button.before( response.data );
				}
			}
		});
	};

	/**
	 * Balance Export Trigger
	 */
	$( '#bonipress-run-exporter' ).click(function(){
		bonipress_action_export_balances( $(this) );
	});

	/**
	 * Generate Key AJAX Caller
	 */
	var bonipress_generate_key = function() {
		$.ajax({
			type     : "POST",
			data     : {
				action  : 'bonipress-action-generate-key',
				token   : boniPRESSmanage.token
			},
			dataType : "JSON",
			url      : boniPRESSmanage.ajaxurl,
			success  : function( response ) {
				$( '#boniPRESS-remote-key' ).val( response.data );
				$( '#bonipress-length-counter' ).text( response.data.length );
			}
		});
	}

	/**
	 * Generate Key Trigger
	 */
	$( '#bonipress-generate-api-key' ).click(function(){
		bonipress_generate_key();
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPRESS-remote-key' ).change(function(){
		$( '#bonipress-length-counter' ).text( $(this).val().length );
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPRESS-remote-key' ).keyup(function(){
		$( '#bonipress-length-counter' ).text( $(this).val().length );
	});
});
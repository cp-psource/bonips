/**
 * boniPS Management Scripts
 * @since 1.3
 * @version 1.2
 */
jQuery(function($) {

	/**
	 * Empty Log AJAX Caller
	 */
	var bonips_action_empty_log = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-action-empty-log',
				token     : boniPSmanage.token,
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success    : function( response ) {
				if ( response.success ) {
					$( 'input#bonips-manage-table-rows' ).val( response.data );
					button.val( boniPSmanage.done );
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
	$( 'input#bonips-manage-action-empty-log' ).on('click', function(){
		// Confirm action
		if ( confirm( boniPSmanage.confirm_log ) )
			bonips_action_empty_log( $(this) );
	});

	/**
	 * Reset Balance AJAX Caller
	 */
	var bonips_action_reset_balance = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-action-reset-accounts',
				token     : boniPSmanage.token,
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPSmanage.working );
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
	$( 'input#bonips-manage-action-reset-accounts' ).on('click', function(){
		// Confirm action
		if ( confirm( boniPSmanage.confirm_reset ) )
			bonips_action_reset_balance( $(this) );
	});

	/**
	 * Export Balances Modal
	 */
	$('#export-points').dialog({
		dialogClass : 'bonips-export-points',
		draggable   : false,
		autoOpen    : false,
		closeText   : boniPSmanage.export_close,
		title       : boniPSmanage.export_title,
		modal       : true,
		width       : 500,
		resizable   : false,
		show        : { effect: 'slide', direction: 'up', duration: 250 },
		hide        : { effect: 'slide', direction: 'up', duration: 250 }
	});

	/**
	 * Export balances Modal Trigger
	 */
	$( '#bonips-export-users-points' ).on('click', function() {
		$( '#export-points' ).dialog( 'open' );
	});

	/**
	 * Export Balances AJAX Caller
	 */
	var bonips_action_export_balances = function( button ) {
		var label = button.val();
		$.ajax({
			type       : "POST",
			data       : {
				action    : 'bonips-action-export-balances',
				token     : boniPSmanage.token,
				identify  : $( '#bonips-export-identify-by' ).val(),
				log_temp  : $( '#bonips-export-log-template' ).val(),
				type      : button.attr( 'data-type' )
			},
			dataType   : "JSON",
			url        : boniPSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success    : function( response ) {
				// Debug
				//console.log( response );

				if ( response.success ) {
					setTimeout(function(){
						window.location.href = response.data;
						button.val( boniPSmanage.done );
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
	$( '#bonips-run-exporter' ).on('click', function(){
		bonips_action_export_balances( $(this) );
	});

	/**
	 * Generate Key AJAX Caller
	 */
	var bonips_generate_key = function() {
		$.ajax({
			type     : "POST",
			data     : {
				action  : 'bonips-action-generate-key',
				token   : boniPSmanage.token
			},
			dataType : "JSON",
			url      : boniPSmanage.ajaxurl,
			success  : function( response ) {
				$( '#boniPS-remote-key' ).val( response.data );
				$( '#bonips-length-counter' ).text( response.data.length );
			}
		});
	}

	/**
	 * Generate Key Trigger
	 */
	$( '#bonips-generate-api-key' ).on('click', function(){
		bonips_generate_key();
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPS-remote-key' ).on('change', function(){
		$( '#bonips-length-counter' ).text( $(this).val().length );
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPS-remote-key' ).keyup(function(){
		$( '#bonips-length-counter' ).text( $(this).val().length );
	});
});
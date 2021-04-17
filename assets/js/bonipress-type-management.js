/**
 * boniPRESS Management Scripts
 * @since 1.3
 * @version 1.4
 */
jQuery(function($) {

	var wWidth     = $(window).width();
	var dWidth     = wWidth * 0.75;

	/**
	 * Make sure new point type key is
	 * correctly formatted. Only lowercase letters and underscores
	 * are allowed. Warn user if needed.
	 */
	$( '#bonipress-new-ctype-key-value' ).on( 'change', function(){

		var ctype_key = $(this).val();
		var re        = /^[a-z_]+$/;
		if ( ! re.test( ctype_key ) ) {
			$(this).css( 'border-color', 'red' );
			$( '#bonipress-ctype-warning' ).css( 'color', 'red' );
		}
		else {
			$(this).css( 'border-color', 'green' );
			$( '#bonipress-ctype-warning' ).css( 'color', '' );
		}

	});

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
	$( '#bonipress-manage-action-empty-log' ).click(function(){

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

				console.log( response );
				if ( response.success ) {
					button.text( response.data );
					button.removeClass( 'button-primary' );
				}
				else {
					button.text( label );
					button.removeAttr( 'disabled' );
					alert( response.data );
				}

			}
		});

	};

	/**
	 * Reset Balance Trigger
	 */
	$( '#bonipress-manage-action-reset-accounts' ).click(function(){

		// Confirm action
		if ( confirm( boniPRESSmanage.confirm_reset ) )
			bonipress_action_reset_balance( $(this) );

	});

	$(document).ready( function() {

		if ( dWidth < 250 )
			dWidth = wWidth;

		if ( dWidth > 960 )
			dWidth = 960;

		/**
		 * Export Balances Modal
		 */
		$( '#export-points' ).dialog({
			dialogClass : 'bonipress-export-points',
			draggable   : true,
			autoOpen    : false,
			title       : boniPRESSmanage.export_title,
			closeText   : boniPRESSmanage.export_close,
			modal       : true,
			width       : dWidth,
			height      : 'auto',
			resizable   : false,
			position    : { my: "center", at: "top+25%", of: window },
			show        : {
				effect     : 'fadeIn',
				duration   : 250
			},
			hide        : {
				effect     : 'fadeOut',
				duration   : 250
			}
		});

		/**
		 * Export balances Modal Trigger
		 */
		$( '#bonipress-export-users-points' ).click( function() {

			$(this).blur();

			$( '#export-points' ).dialog( 'open' );

		});

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

	};

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

	/**
	 * Adjust Decimals AJAX Caller
	 */
	var bonipress_adjust_max_decimals = function( button, label, decval ) {

		$.ajax({
			type     : "POST",
			data     : {
				action   : 'bonipress-action-max-decimals',
				token    : boniPRESSmanage.token,
				decimals : decval
			},
			dataType : "JSON",
			url      : boniPRESSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPRESSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success  : function( response ) {

				if ( response.success ) {
					button.val( response.data.label );
					setTimeout(function(){
						window.location.href = response.data.url;
					}, 4000 );
				}
				else {
					button.val( response.data );
					setTimeout(function(){
						button.removeAttr( 'disabled' );
						button.val( label );
					}, 4000 );
				}

			}
		});

	};

	/**
	 * Show / Hide Update Button
	 */
	$( '#bonipress-adjust-decimal-places' ).change(function(){

		var originaldec = $(this).data( 'org' );
		var newvalue    = $(this).val();

		if ( originaldec != newvalue )
			$( '#bonipress-update-log-decimals' ).show();
		else
			$( '#bonipress-update-log-decimals' ).hide();

	});

	/**
	 * Update Log Decimals Trigger
	 */
	$( '#bonipress-update-log-decimals' ).click(function(){

		if ( confirm( boniPRESSmanage.decimals ) ) {
			bonipress_adjust_max_decimals( $(this), $(this).val(), $( '#bonipress-adjust-decimal-places' ).val() );
		}

	});

	var clearing_cache = false;

	/**
	 * Cache Clearing
	 */
	var bonipress_clear_the_cache = function( button, label ) {

		if ( clearing_cache ) return false;

		clearing_cache = true;

		$.ajax({
			type     : "POST",
			data     : {
				action   : 'bonipress-action-clear-cache',
				token    : boniPRESSmanage.cache,
				ctype    : button.attr( 'data-type' ),
				cache    : button.attr( 'data-cache' )
			},
			dataType : "JSON",
			url      : boniPRESSmanage.ajaxurl,
			beforeSend : function() {
				button.html( boniPRESSmanage.working );
				button.attr( 'disabled', 'disabled' );
			},
			success  : function( response ) {

				alert( response.data );
				button.html( label );

			},
			complete : function() {
				clearing_cache = false;
			}
		});

	};

	/**
	 * Clear Cache Trigger
	 */
	$( 'button.clear-type-cache-button' ).click(function(){

		bonipress_clear_the_cache( $(this), $(this).html() );

	});

});
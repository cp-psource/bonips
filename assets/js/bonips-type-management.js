/**
 * boniPS Management Scripts
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
	$( '#bonips-new-ctype-key-value' ).on( 'change', function(){

		var ctype_key = $(this).val();
		var re        = /^[a-z_]+$/;
		if ( ! re.test( ctype_key ) ) {
			$(this).css( 'border-color', 'red' );
			$( '#bonips-ctype-warning' ).css( 'color', 'red' );
		}
		else {
			$(this).css( 'border-color', 'green' );
			$( '#bonips-ctype-warning' ).css( 'color', '' );
		}

	});

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
	$( '#bonips-manage-action-empty-log' ).on('click', function(){

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
	$( '#bonips-manage-action-reset-accounts' ).on('click', function(){

		// Confirm action
		if ( confirm( boniPSmanage.confirm_reset ) )
			bonips_action_reset_balance( $(this) );

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
			dialogClass : 'bonips-export-points',
			draggable   : true,
			autoOpen    : false,
			title       : boniPSmanage.export_title,
			closeText   : boniPSmanage.export_close,
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
		$( '#bonips-export-users-points' ).click( function() {

			$(this).blur();

			$( '#export-points' ).dialog( 'open' );

		});

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

	};

	/**
	 * Generate Key Trigger
	 */
	$( '#bonips-generate-api-key' ).on('click', function(){
		bonips_generate_key();
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPS-remote-key' ).change(function(){
		$( '#bonips-length-counter' ).text( $(this).val().length );
	});

	/**
	 * Key Length Indicator
	 */
	$( '#boniPS-remote-key' ).keyup(function(){
		$( '#bonips-length-counter' ).text( $(this).val().length );
	});

	/**
	 * Adjust Decimals AJAX Caller
	 */
	var bonips_adjust_max_decimals = function( button, label, decval ) {

		$.ajax({
			type     : "POST",
			data     : {
				action   : 'bonips-action-max-decimals',
				token    : boniPSmanage.token,
				decimals : decval
			},
			dataType : "JSON",
			url      : boniPSmanage.ajaxurl,
			beforeSend : function() {
				button.attr( 'value', boniPSmanage.working );
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
	$( '#bonips-adjust-decimal-places' ).change(function(){

		var originaldec = $(this).data( 'org' );
		var newvalue    = $(this).val();

		if ( originaldec != newvalue )
			$( '#bonips-update-log-decimals' ).show();
		else
			$( '#bonips-update-log-decimals' ).hide();

	});

	/**
	 * Update Log Decimals Trigger
	 */
	$( '#bonips-update-log-decimals' ).on('click', function(){

		if ( confirm( boniPSmanage.decimals ) ) {
			bonips_adjust_max_decimals( $(this), $(this).val(), $( '#bonips-adjust-decimal-places' ).val() );
		}

	});

	var clearing_cache = false;

	/**
	 * Cache Clearing
	 */
	var bonips_clear_the_cache = function( button, label ) {

		if ( clearing_cache ) return false;

		clearing_cache = true;

		$.ajax({
			type     : "POST",
			data     : {
				action   : 'bonips-action-clear-cache',
				token    : boniPSmanage.cache,
				ctype    : button.attr( 'data-type' ),
				cache    : button.attr( 'data-cache' )
			},
			dataType : "JSON",
			url      : boniPSmanage.ajaxurl,
			beforeSend : function() {
				button.html( boniPSmanage.working );
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
	$( 'button.clear-type-cache-button' ).on('click', function(){

		bonips_clear_the_cache( $(this), $(this).html() );

	});

});